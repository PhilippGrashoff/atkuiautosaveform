<?php declare(strict_types=1);

namespace PhilippR\Atk4\AutoSaveForm;

use Atk4\Data\ValidationException;
use Atk4\Ui\Exception;
use Atk4\Ui\Form;
use Atk4\Ui\Form\Control;
use Atk4\Ui\Form\Control\Dropdown;
use Atk4\Ui\Form\Control\Line;
use Atk4\Ui\Form\Control\Textarea;
use Atk4\Ui\Js\Jquery;
use Atk4\Ui\Js\JsBlock;
use Atk4\Ui\Js\JsExpression;
use Atk4\Ui\Js\JsExpressionable;
use Atk4\Ui\View;
use Closure;

class AutoSaveForm extends Form
{
    /**
     * @throws Exception
     */
    protected function renderView(): void
    {
        //in renderView as $this->buttonSave might be modified after init()
        $this->setApiConfig(
            [
                'stateContext' => $this->buttonSave,
                'loadingDuration' => '300',
                'interruptRequests' => true
            ]
        );

        foreach ($this->controls as $control) {
            $this->addAutoSubmitToControl($control);
        }
        parent::renderView();
    }

    /**
     * Calls the fitting addAutoSubmit... method depending on the control type
     * @throws Exception
     */
    protected function addAutoSubmitToControl(Form\Control $control): void
    {
        match (get_class($control)) {
            Dropdown::class => $this->addAutoSubmitToDropdown($control),
            Textarea::class => $this->addAutoSubmitToTextarea($control),
            Line::class => $this->addAutoSubmitToLine($control),
            default => $this->addOnChangeEventToControl($control)
        };
    }

    protected function addAutoSubmitToDropdown(Dropdown $control): void
    {
        $this->addOnChangeEventToControl($control);
    }

    protected function addAutoSubmitToLine(Line $control): void
    {
        $this->addFocusOutEventToControl($control);
        $this->addKeyUpEventsToControl($control);
    }

    protected function addAutoSubmitToTextarea(Textarea $control): void
    {
        $this->addFocusOutEventToControl($control, 'textarea');
        $this->addKeyUpEventsToControl($control, 'textarea');
    }

    /**
     * TODO think about existing onChange that might be attached to the dropdown already. Maybe better just use
     * a JQuery to add an additional onChange event to Dropdown?
     *
     * @param Control $control
     * @return void
     * @throws Exception
     */
    protected function addOnChangeEventToControl(Control $control): void
    {
        $control->on(
            'change',
            new JsBlock(
                [
                    (new Jquery($this->buttonSave))->removeClass('basic'),
                    $this->js()->form('submit')
                ]
            )
        );
    }

    /**
     * This could probably be removed if a well-working keypress / keyup handler is implemented for text fields
     *
     * @param Control $control
     * @param string $inputTag
     * @return void
     * @throws Exception
     */
    protected function addFocusOutEventToControl(Control $control, string $inputTag = 'input'): void
    {
        $control->setAttr('data-initialvalue', $control->getValue());
        $control->on(
            'focusout',
            new JsExpression (
                'if([control].data("initialvalue") !== [inputValue]) {'
                . '[submitForm]; [control].data("initialvalue", [inputValue]); 
                    };',
                [
                    'control' => (new Jquery($control)),
                    'inputValue' => (new Jquery($control))->children($inputTag)->val(),
                    'submitForm' => $this->js()->form('submit'),
                ]
            )
        );
    }

    /**
     * @param Control $control
     * @param string $inputTag
     * @return void
     * @throws Exception
     */
    protected function addKeyUpEventsToControl(Control $control, string $inputTag = 'input'): void
    {
        $control->on(
            'keyup',
            new JsExpression(
                'if([control].data("initialvalue") !== [inputValue]) {'
                . '[savebutton].removeClass("basic");}'
                . 'else {[savebutton].addClass("basic");'
                .' atk.createDebouncedFx((evt) => {console.log("fsdfsfs");}, 250);}', //TODO this does not work at the moment. Find a way to add a debounced action without additional js file needed
                [
                    'control' => (new Jquery($control)),
                    'inputValue' => (new Jquery($control))->children($inputTag)->val(),
                    'savebutton' => (new Jquery($this->buttonSave))
                ],
            )
        );
    }

    /**
     * Modified version of Form::onSubmit. Adds additional JsExpressionables to the return value in case of success to
     * 1) "un-highlight" the save button
     * 2) update control values in case they were modified in data layer during save()
     *
     * @param Closure($this): (JsExpressionable|View|string|void) $fx
     *
     * @return $this
     */
    public function onSubmit(Closure $fx): static
    {
        $this->onHook(self::HOOK_SUBMIT, $fx);

        $this->cb->set(function () {
            try {
                $this->loadPost();
                $valuesBeforeSave = $this->entity->get();
                $response = $this->hook(self::HOOK_SUBMIT);

                $modifiedResponse = new JsBlock([]);
                if (is_array($response)) {
                    foreach ($response as $value) {
                        if ($value instanceof JsExpressionable) {
                            $modifiedResponse->addStatement($value);
                        }
                    }
                }

                //add basic class to save button in case of success
                $modifiedResponse->addStatement((new Jquery($this->buttonSave))->addClass('basic'));

                //check which model fields do not match the passed control values an update them accordingly by JS
                $this->jsSetUpdatedValuesToControls($modifiedResponse, $valuesBeforeSave);

                return $modifiedResponse;
            } catch (ValidationException $e) {
                $response = new JsBlock();
                foreach ($e->errors as $field => $error) {
                    if (!isset($this->controls[$field])) {
                        throw $e;
                    }

                    $response->addStatement($this->jsError($field, $error));
                }

                return $response;
            }
        });

        return $this;
    }

    /**
     * checks if any field values do not match the control values before the Submit hook (usually saves model).
     * If so, it adds the corresponding JS actions to the response, so controls are updated and represent the current
     * field values.
     *
     * @param JsBlock $response
     * @param array $valuesBeforeSave
     * @return void
     * @throws \Atk4\Core\Exception
     */
    protected function jsSetUpdatedValuesToControls(JsBlock $response, array $valuesBeforeSave): void
    {
        $changedFields = $this->getChangedFields($valuesBeforeSave);
        if (!$changedFields) {
            return;
        }
        foreach ($changedFields as $control) {
            $response->addStatement(
                $this->jsUpdateControlValue($control)
            );
        }
    }

    protected function jsUpdateControlValue(Control $control): JsExpressionable
    {
        if ($control instanceof Dropdown) {
            return new JsBlock(
                [
                    (new Jquery($control))->children('.ui.dropdown')
                        ->dropdown('set selected', $control->getValue(), true),
                    $this->getFieldChangedAnimation('#' . $control->getHtmlId() . ' .ui.dropdown')
                ]
            );
        } elseif ($control instanceof Textarea) {
            return new JsBlock(
                [
                    $control->jsInput()->val($control->getValue()),
                    $this->getFieldChangedAnimation('#' . $control->getHtmlId() . ' textarea')
                ]
            );
        }
        return new JsBlock(
            [
                $control->jsInput()->val($control->getValue()),
                $this->getFieldChangedAnimation('#' . $control->getHtmlId() . '_input')
            ]
        );
    }

    protected function getFieldChangedAnimation(string $querySelector): JsExpression
    {
        return new JsExpression(
            'document.querySelector("' . $querySelector . '").animate('
            . '[{"background": "palegreen"}, {"background": "#ffffff"}], {duration: 4000, easing: "ease-out"});'
        );
    }

    /**
     * Checks which field values of the model do not match the submitted values anymore. This means that the values
     * were changed by data layer during Model::save().
     *
     * @param array $valuesBeforeSave
     * @return array
     * @throws \Atk4\Core\Exception
     */
    protected function getChangedFields(array $valuesBeforeSave): array
    {
        $changedFields = [];

        foreach ($this->controls as $fieldName => $control) {
            //ignore readonly and disabled controls
            if ($control->readOnly || $control->disabled) {
                continue;
            }
            /* typecastSaveField() here for two reasons:
            * 1) to handle Date fields correctly - they need to be modified to a scalar value for proper comparison
            * 2) Dropdown values - otherwise there is string to int comparison, e.g. when using $control->getValue()
            */
            if (
                $this->getApp()->uiPersistence->typecastSaveField(
                    $control->entityField->getField(),
                    $valuesBeforeSave[$fieldName]
                )
                !== $this->getApp()->uiPersistence->typecastSaveField(
                    $control->entityField->getField(),
                    $control->entityField->get()
                )
            ) {
                $changedFields[] = $control;
            }
        }

        return $changedFields;
    }
}