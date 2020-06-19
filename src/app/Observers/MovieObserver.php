<?php

namespace App\Observers;

use App\Models\Movie;
use App\Models\MovieLog;
use Illuminate\Contracts\Auth\Factory as Auth;

class MovieObserver
{

    protected $auth;
    protected $logs;
    public function __construct(Auth $auth,MovieLog $logs)
    {
        $this->auth = $auth;
        $this->logs = $logs;
    }

    /**
     * Handle the movie "updated" event.
     *
     * @param  \App\Models\Movie  $movie
     * @return void
     */
    public function updated(Movie $movie)
    {
        $fields_tracked = [
            'title',
            'rental_price',
            'sale_price'
        ];

        $user = $this->auth->user();
        $newLogs = collect(array_keys($movie->getDirty()))
            ->filter(function ($field) use ($fields_tracked) {
                return in_array($field, $fields_tracked);
            })
            ->map(function ($field) use ($movie, $user) {
                return [
                    'old_value' => $movie->getOriginal($field),
                    'new_value' => $movie->{$field},
                    'field' => $field,
                    'user_id' => $user->id,
                    'movie_id' => $movie->id
                ];

            })->toArray();
        $this->logs->insert($newLogs);
    }


}