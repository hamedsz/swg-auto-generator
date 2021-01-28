<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SWG extends Command
{
    public $requests = [];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swg:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(function ($route) {
                return Str::contains($route->uri(), "api/") && Str::contains($route->getName(), "req:");
            })
            ->each(function (Route $item, $key) {

                $namespace = str_replace("req:", "", $item->getName());
                try {
                    $object = new $namespace;
                    $rules = $object->rules();
                    $item->rules = $rules;
                } catch (Error $error) {
                    $item->rules = [];
                }
            });

        Artisan::call("l5-swagger:generate");

        $docs = file_get_contents(storage_path('api-docs/api-docs.json'));
        $docsJson = json_decode($docs);
        $paths = (array)$docsJson->paths;
        $schemas = (array)$docsJson->components->schemas;

        foreach ($routes as $route) {
            $uri = str_replace("api", "", $route->uri());
            $method = strtolower($route->methods[0]);
            $parameters = [];

            $paths[$uri] = [
                $method => [
                    'tags' => [
                        $route->getPrefix()
                    ],
                    'summery' => 'test summery',
                    'description' => '',
                    'responses' => [
                        '200' => [
                            "description" => "Successful",
                            'content' => [
                                'application/json' => [
                                    'schema' => []
                                ]
                            ]
                        ],
                        '204' => [
                            "description" => "Successful",
                        ],
                        '400' => [
                            "description" => "Bad Request",
                        ],
                        '404' => [
                            "description" => "Not Found",
                        ],
                    ],
                    'security' => [
                        [
                            'bearerAuth' => []
                        ]
                    ]
                ]
            ];

            //generate request body
            if ($method == 'post' || $method == "put") {

                $schemaName = Str::random(10);
                $properties = [];

                foreach ($route->rules as $key => $rule) {
                    $properties[$key] = [
                        'title' => $key,
                        'description' => '',
                        'type' => 'string',
                        'format' => 'format',
                    ];
                }

                $schemas[$schemaName] = [
                    'title' => $schemaName,
                    'description' => "",
                    'required' => [],
                    "type" => 'object',
                    'properties' => $properties
                ];

                $paths[$uri][$method]['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $schemaName
                            ]
                        ]
                    ]
                ];
            } else {
                $parameters = [];
                foreach ($route->rules as $key => $rule) {
                    $parameters[] = [
                        'name' => $key,
                        'in' => 'query',
                        'description' => '',
                        'required' => false,
                        'explode' => true,
                    ];
                }

            }

            $parameters = $parameters ?? [];
            foreach ($route->parameterNames() as $parameterName) {
                $parameters[] = [
                    'name' => $parameterName,
                    'in' => 'path',
                    'description' => '',
                    'required' => true,
                    'explode' => true,
                ];
            }

            $paths[$uri][$method]['parameters'] = $parameters;
        }

        $docsJson->paths = $paths;
        $docsJson->components->schemas = $schemas;
        $docs = json_encode($docsJson);

        file_put_contents(storage_path('api-docs/api-docs.json'), $docs);
    }
}
