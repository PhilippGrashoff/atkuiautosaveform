<?php

namespace PhilippR\Atk4\AutoSaveForm\Tests\testfiles;

use Atk4\Data\Model;

class DemoModel2 extends Model
{
    public $table = 'demo_model_2';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');

        $this->hasMany(DemoModel::class, ['model' => [DemoModel::class]]);
    }
}