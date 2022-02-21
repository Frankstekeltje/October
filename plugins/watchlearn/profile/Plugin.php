<?php namespace Watchlearn\Profile;

use System\Classes\PluginBase;
use Rainlab\User\Controllers\Users as UsersController;
use Rainlab\User\Models\User as UserModel;

class Plugin extends PluginBase
{
    public function registerComponents()
    {
    }

    public function registerSettings()
    {
    }

    public function boot(){

        UserModel::extend(function ($model){
            $model->addFillable([
                'facebook',
                'bio'
            ]);
        });

        UsersController::extendFormFields(function($form,$model,$context){
            $form->addTabFields([
               'facebook' => [
                   'label' => 'facebook',
                   'type' => 'text',
                   'tab' => 'profile'
               ],
                'bio' => [
                    'label' => 'bio',
                    'type' => 'textarea',
                    'tab' => 'profile'
                ]
            ]);
        });
    }
}
