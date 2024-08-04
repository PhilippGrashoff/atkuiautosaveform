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
        $this->addField('datetime', ['type' => 'datetime', 'default' => $this->removeNanoSeconds(new \DateTime())]);
        $this->addField('date', ['type' => 'date', 'default' => new \DateTime()]);
        $this->addField('time', ['type' => 'time', 'default' => $this->removeNanoSeconds(new \DateTime())]);
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

        $this->hasOne('demo_model_2_id', ['model' => [DemoModel2::class], 'caption' => 'Lookup']);

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
        $this->set('checkbox', !$this->get('checkbox'));
        $this->set('datetime', $this->removeNanoSeconds((new \DateTime())->modify('+ ' . rand(1, 100) . ' Days')));
        $this->set('date', (new \DateTime())->modify('+ ' . rand(1, 100) . ' Days'));
        $this->set('time', $this->removeNanoSeconds((new \DateTime())->modify('+ ' . rand(1, 1000) . ' Minutes')));
        $this->set('dropdown', $this->get('dropdown') === 2 ? 1 : 2);
        $this->set('radio', $this->get('radio') === 2 ? 1 : 2);
        $this->set('demo_model_2_id', rand(0,9));
    }


    public function removeNanoSeconds(\DateTimeInterface $dateTime): \DateTimeInterface
    {
        if (!$dateTime instanceof \DateTime) {
            throw new \InvalidArgumentException('Invalid date object.');
        }

        return $dateTime->setTime(
            (int) $dateTime->format('G'), // hours
            (int) $dateTime->format('i'), // minutes
            (int) $dateTime->format('s')  // seconds
        );
    }
}