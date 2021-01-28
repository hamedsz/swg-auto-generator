<?php

namespace SwgAuthGenerator;

use Illuminate\Support\ServiceProvider;


class SwgAutoGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            SWG::class
        ]);
    }
}