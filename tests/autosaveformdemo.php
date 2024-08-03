<?php

require_once "../vendor/autoload.php";

use Atk4\Data\Persistence;
use Atk4\Data\Schema\Migrator;
use Atk4\Ui\App;
use Atk4\Ui\Js\JsBlock;
use Atk4\Ui\Js\JsToast;
use Atk4\Ui\Layout\Centered;
use PhilippR\Atk4\AutoSaveForm\AutoSaveForm;
use PhilippR\Atk4\AutoSaveForm\Tests\testfiles\DemoModel;

$persistence = Persistence::connect('sqlite::memory:');
$demoModel = new DemoModel($persistence);
(new Migrator($demoModel))->create();
$entity = $demoModel->createEntity()->save();

$app = new App(['title' => 'AutoSaveForm Demo']);
$app->initLayout([Centered::class]);

$autoSaveForm = AutoSaveForm::addTo($app);
$autoSaveForm->setModel($entity);

$autoSaveForm->onSubmit(static function ($form) use ($app) {
    $form->entity->save();
    /*$return = new JsBlock([]);
    foreach ($form->entity->get() as $fieldName => $value) {
        $return->addStatement(new JsToast($fieldName . ': ' . $app->uiPersistence->typecastSaveField($form->entity->getField($fieldName), $value)));
    }

    return $return;*/
});