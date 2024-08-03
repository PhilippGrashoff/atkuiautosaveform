<?php

namespace PhilippR\Atk4\AutoSaveForm\Tests\testfiles;

use Atk4\Data\Model;
use Atk4\Ui\Form\Control\Radio;

class DemoModel extends Model
{
    public $table = 'demo_model';

    protected function init(): void
    {
        parent::init();
        $this->addField('line', ['type' => 'string', 'default' => 'line']);
        $this->addField('textarea', ['type' => 'text', 'default' => 'textarea']);
        $this->addField('checkbox', ['type' => 'boolean', 'default' => true]);
        $this->addField('datetime', ['type' => 'datetime', 'default' => new \DateTime()]);
        $this->addField('date', ['type' => 'date', 'default' => new \DateTime()]);
        $this->addField('time', ['type' => 'time', 'default' => new \DateTime()]);
        $this->addField(
            'dropdown',
            [
                'type' => 'integer',
                'values' => [0 => 'No', 1 => 'Yes', 2 => 'Maybe'],
                'default' => 2
            ]
        );
        $this->addField(
            'radio',
            [
                'type' => 'integer',
                'values' => [0 => 'No', 1 => 'Yes', 2 => 'Maybe'],
                'default' => 2,
                'ui' => ['form' => [Radio::class]]
            ]
        );
        $this->addField('change', ['type' => 'boolean', 'caption' => 'Change Field values during save()']);
        $this->onHook(
            Model::HOOK_BEFORE_SAVE,
            static function (self $entity) {
                if ($entity->get('change')) {
                    $entity->changeFieldValues();
                }
            }
        );
    }

    protected function changeFieldValues(): void
    {
        $this->set('line', $this->get('line') . ' changed');
        $this->set('textarea', $this->get('textarea') . ' changed');
        //TODO CheckBox
        $this->set('datetime', $this->get('datetime') ? $this->get('datetime')->modify('+ 1 Day') : new \DateTime());
        $this->set('date', $this->get('date') ? $this->get('date')->modify('+ 1 Day') : new \DateTime());
        $this->set('time', $this->get('time') ? $this->get('time')->modify('+ 1 Minute') : new \DateTime());
        $this->set('dropdown', $this->get('dropdown') === 2 ? 1 : 2);
        $this->set('radio', $this->get('radio') === 2 ? 1 : 2);
    }
}