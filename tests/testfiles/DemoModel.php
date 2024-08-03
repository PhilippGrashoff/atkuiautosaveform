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
        $this->addField('line');
        $this->addField('textarea', ['type' => 'text']);
        $this->addField('checkbox', ['type' => 'boolean']);
        $this->addField('datetime', ['type' => 'datetime']);
        $this->addField('date', ['type' => 'date']);
        $this->addField('time', ['type' => 'time']);
        $this->addField('dropdown', ['type' => 'integer', 'values' => [0 => 'No', 1 => 'Yes', 2 => 'Maybe']]);
        $this->addField(
            'radio',
            [
                'type' => 'integer',
                'values' => [0 => 'No', 1 => 'Yes', 2 => 'Maybe'],
                'ui' => ['form' => [Radio::class]]
            ]
        );
    }
}