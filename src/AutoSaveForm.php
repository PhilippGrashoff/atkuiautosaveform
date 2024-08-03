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

/**
 * This Form extension does 2 things:
 * 1) it automatically submits if a user changes a control value, e.g. selects a dropdown or types some text into
 *    a text input.
 * 2) If a field value is updated within form submit (e.g. if Model::save() modifies field values before saving),
 *    the AutoSaveForm updates the matching controls accordingly.
 *
 * To create a proper UI experience, the save button of the form is used. It has 3 states:
 * 1) "Initial": Only a colored outline (using FUI`s "basic" class). This means that no value within the form was changed
 *    - the initial state when the form is loaded.
 * 2) "Highlighted": A colored background. Indicates that changes to a value were detected.
 * 3) "Highlighted and loading" The button is colored and has a loading animation on it. This indicates that the form
 *    submission is happening.
 *
 * //TODO add a link to a small youtube video when this is more finished.
 */
class AutoSaveForm extends Form
{
    /**
     * @var string The string that is passed to .animate() to indicate that a control value was updated as a result of
     * the form submission.
     * Mor about .animate(): https://developer.mozilla.org/en-US/docs/Web/API/Web_Animations_API/Using_the_Web_Animations_API
     */
    public string $animationOnJsValueUpdate = '[{"background": "palegreen"}, {"background": "#ffffff"}], {duration: 4000, easing: "ease-out"}';

    /**
     * @var int $loadingDuration how long the "loading" state is at least applied.
     * A minimum of about 300ms is needed in order to give the user a proper UI feedback that form is submitted
     */
    public int $loadingDuration = 300;

    /**
     * 1) set the stateContext (where the "loading" class is added to on form submit). For AUtoSaveForm, setting the
     *    stateContext to the save button is sensible. Otherwise, the whole form is displayed as loading on each form submit.
     *    $this->buttonSave might be modified after init(), hence this is put into renderView()
     * 2) add according event listeners to each control so this Form is submitted if the control is changed.
     * @return void
     * @throws Exception
     */
    protected function renderView(): void
    {
        $this->setApiConfig(
            [
                'stateContext' => $this->buttonSave,
                'loadingDuration' => $this->loadingDuration
            ]
        );

        foreach ($this->controls as $control) {
            $this->addAutoSubmitToControl($control);
        }

        $this->buttonSave->addClass('basic');

        parent::renderView();
    }

    /**
     * add the fitting event handlers for automatic form submit depending on the control type
     * TODO check against all atk4 native input types if the action is correct/sensible.
     * @throws Exception
     */
    protected function addAutoSubmitToControl(Form\Control $control): void
    {
        match (get_class($control)) {
            Textarea::class => $this->addKeyUpEventsToControl($control, 'textarea'),
            Line::class => $this->addKeyUpEventsToControl($control),
            default => $this->addOnChangeEventToControl($control)
        };
    }

    /**
     * TODO think about existing onChange that might be attached to the dropdown/control already. Maybe better just use
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
                '
                if([control].data("initialvalue") !== [inputValue]) {
                    [savebutton].removeClass("basic");
                    clearTimeout([control].data("timerId"));
                    let timer = setTimeout(() => {
                        [submitForm]; [control].data("initialvalue", [inputValue]);
                    }, 700);
                    [control].data("timerId", timer);
                }
                else {
                    [savebutton].addClass("basic");
                    clearTimeout([control].data("timerId"));
                }',
                [
                    'control' => (new Jquery($control)),
                    'inputValue' => (new Jquery($control))->children($inputTag)->val(),
                    'savebutton' => (new Jquery($this->buttonSave)),
                    'submitForm' => $this->js()->form('submit'),
                ],
            ),


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
                    $this->jsFieldChangedAnimation('#' . $control->getHtmlId() . ' .ui.dropdown')
                ]
            );
        } elseif ($control instanceof Textarea) {
            return new JsBlock(
                [
                    $control->jsInput()->val($control->getValue()),
                    $this->jsFieldChangedAnimation('#' . $control->getHtmlId() . ' textarea'),
                    $this->jsUpdateInitialValue($control)
                ]
            );
        } elseif ($control instanceof Line) {
            return new JsBlock(
                [
                    $control->jsInput()->val($control->getValue()),
                    $this->jsFieldChangedAnimation('#' . $control->getHtmlId() . '_input'),
                    $this->jsUpdateInitialValue($control)
                ]
            );
        }
        return new JsBlock(
            [
                $control->jsInput()->val($control->getValue()),
                $this->jsFieldChangedAnimation('#' . $control->getHtmlId() . '_input'),
            ]
        );
    }

    protected function jsUpdateInitialValue(Control $control): JsExpression
    {
        return (new Jquery($control))->data('initialvalue', $control->getValue());
    }

    protected function jsFieldChangedAnimation(string $querySelector): JsExpression
    {
        return new JsExpression(
            'document.querySelector("' . $querySelector . '").animate(' . $this->animationOnJsValueUpdate . ');'
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