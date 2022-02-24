<?php

use Watchlearn\Movies\Models\Movie;
use Watchlearn\Movies\Models\Actor;
use Watchlearn\Movies\Models\Genre;

Route::get('seed-actors', function(){

    $faker = Faker\Factory::create();
    for($i = 0; $i < 100; $i++){
        Actor::create([
            'name' => $faker->firstName,
            'lastname' => $faker->lastName
        ]);
    }

        return "Actors created!";
});

Route::get('/create-movies', function(){
   $faker = Faker\Factory::create();

   for($i = 0; $i < 15; $i++){
       $name = $faker->name;

       Movie::create([
           'name' => $name,
           'description' => $faker->paragraph($nbSentences = 3, $variableNbSentences = true),
           'slug' => str_slug($name, '-'),
       ]);
   }
   return "movies added!";
});

Route::get('/populate-movies', function(){
    $faker = Faker\Factory::create();

    $movies = Movie::all();

    foreach ($movies as $movie){
        $genres = Genre::all()->random(2);

        $movie->genres = $genres;
        $movie->year = $faker->year($format= 'Y', $max = 'now');

        $movie->created_at = $faker->date($format = 'Y-m-d H:i:s', $max = 'now');
        $movie->published = $faker->boolean($chanceOfGettingTrue = 50);
        $movie->save();
    }
});
