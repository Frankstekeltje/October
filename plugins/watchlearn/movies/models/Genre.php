<?php namespace Watchlearn\Movies\Models;

use Model;

/**
 * Model
 */
class Genre extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /*
     * Disable timestamps by default.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'watchlearn_movies_genres';

    public $belongsToMany =[
        'movies' => [
            'watchlearn\Movies\Models\Movie',
            'table' => 'watchlearn_movies_movies_genres',
            'order' => 'name'
        ]
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
