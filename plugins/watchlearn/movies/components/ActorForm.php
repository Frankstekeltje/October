<?php namespace Watchlearn\Movies\Components;

use Cms\classes\ComponentBase;
use Input;
use Validator;
use Redirect;
use Watchlearn\Movies\Models\Actor;
use Flash;


class ActorForm extends ComponentBase{

    public function componentDetails()
    {
        return [
          'name' => 'Actor Form',
          'description' => 'Enter actors'
        ];
    }

    public function onSave(){

        $actor = new Actor();

        $actor->name = Input::get('name');
        $actor->lastname = Input::get('lastname');
        $actor->save();

        Flash::success('Actor Added!');

        return Redirect::back();
    }
}