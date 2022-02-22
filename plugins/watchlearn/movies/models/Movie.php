<?php namespace Watchlearn\Movies\Models;

use Model;

/**
 * Model
 */
class Movie extends Model
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
    public $table = 'watchlearn_movies_';

    //protected $jsonable = ['actors'];

    /* Relations */

    public $belongsToMany =[
        'genres' => [
            'watchlearn\Movies\Models\Genre',
            'table' => 'watchlearn_movies_movies_genres',
            'order' => 'genre_title'
        ],

        'actors' => [
            'watchlearn\Movies\Models\Actor',
            'table' => 'watchlearn_movies_actors_movies',
            'order' => 'name'
        ]

    ];

    public $attachOne = [
        'poster' => 'System\Models\File'
    ];

    public $attachMany = [
        'movie_gallery' => 'System\Models\File'
    ];


    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
