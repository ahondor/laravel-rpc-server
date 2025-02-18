<?php

declare(strict_types=1);

namespace Sajya\Server;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use ReflectionClass;
use ReflectionMethod;
use Sajya\Server\Annotations\Param;
use Sajya\Server\Attributes\RpcMethod;

class Docs
{
    /**
     * @var string[]
     */
    protected $procedures;

    /**
     * @var string
     */
    protected $delimiter;

    /**
     * Docs constructor.
     *
     * @param Route $route
     */
    public function __construct(protected Route $route)
    {
        $this->procedures = $route->defaults['procedures'];
        $this->delimiter = $route->defaults['delimiter'] ?? '@';
    }

    public function toArray(array $mergeData = [])
    {
        $procedures = collect($this->getAnnotationsJson())->map(function($procedure) {
            $name = $procedure['name'] . $procedure['delimiter'] . $procedure['method'];

            return [
                $name => [
                    "envelope"   => "JSON-RPC-2.0",
                    "transport"  => "POST",
                    "name"       => $name,
                    "parameters" => $procedure['parameters'],
                    "returns"    => $procedure['returns']
                ]
            ];
        });

        return [
            "transport"   => "POST",
            "envelope"    => "JSON-RPC-2.0",
            "contentType" => "application/json",
            "SMDVersion"  => "2.0",
            "description" => null,
            "target"      => $this->route->uri(),
            "services"    => $procedures,
            "methods"     => $procedures
        ];
    }

    /**
     * @param  string $blade
     * @param  array  $mergeData
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */
    public function view(string $blade = null, array $mergeData = [])
    {
        return view($blade ?? 'sajya::docs-cards', [
            'title'      => config('app.name'),
            'uri'        => config('app.url').$this->route->uri(),
            'procedures' => $this->getAnnotations(),
        ] + $mergeData);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getAnnotationsJson(): Collection
    {
        return collect($this->procedures)
            ->map(function (string $class) {
                $reflectionClass = new ReflectionClass($class);
                $name = $reflectionClass->getProperty('name')->getValue();

                return collect($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC))
                    ->filter(fn ($method) => $method->getName() !== '__construct')
                    ->map(function (ReflectionMethod $method) use ($name) {

                        $params = $this->getAnnotationsFrom($method, Param::class)
                            ->map(fn (object $param) => $param->toArray());
                        $results = $this->getAnnotationsFrom($method, Result::class)
                            ->map(fn (object $result) => $result->toArray());

                        $factory = DocBlockFactory::createInstance();
                        $comment = $method->getDocComment();
                        $docblock = $factory->create($comment === false ? ' ' : $comment);
                        $description = $docblock->getSummary();

                        return [
                            'name'        => $name,
                            'description' => $description,
                            'delimiter'   => $this->delimiter,
                            'method'      => $method->getName(),
                            'parameters'  => $params,
                            'returns'     => $results
                        ];
                    });
            })
            ->flatten(1);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getAnnotations(): Collection
    {
        return collect($this->procedures)
            ->map(function (string $class) {
                $reflectionClass = new ReflectionClass($class);
                $name = $reflectionClass->getProperty('name')->getValue();

                return collect($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC))
                    ->filter(fn ($method) => $method->getName() !== '__construct')
                    ->map(function (ReflectionMethod $method) use ($name) {

                        $attributes = $this->getMethodAnnotations($method);

                        $request = [
                            'jsonrpc' => '2.0',
                            'id'      => 1,
                            'method'  => $name.$this->delimiter.$method->getName(),
                            'params'  => collect($this->getMethodAnnotations($method, Param::class))->mapWithKeys(function($param) {
                                return [$param['name'] => $param['type']];
                            }),
                            // 'params'  => $attributes?->params,
                        ];

                        $response = [
                            'jsonrpc' => '2.0',
                            'id'      => 1,
                            'result'  => collect($this->getMethodAnnotations($method, Result::class))->map(function($param) {
                                return $param['type'];
                            })->first(),
                            // 'result'  => $attributes?->result,
                        ];

                        return [
                            'name'        => $name,
                            'delimiter'   => $this->delimiter,
                            'method'      => $method->getName(),
                            'description' => $attributes?->description,
                            'params'      => $attributes?->params,
                            'result'      => $attributes?->result,
                            'request'     => $this->highlight($request),
                            'response'    => $this->highlight($response),
                        ];
                    });
            })
            ->flatten(1);
    }

    private function getMethodAnnotations(ReflectionMethod $method): ?RpcMethod
    {
        $attributes = $method->getAttributes(RpcMethod::class);

        // $values = $this
        //     ->getAnnotationsFrom($method, $class)
        //     ->map(fn (object $param) => $param->toArray());
        foreach ($attributes as $attribute) {
            /** @var RpcMethod $instance */
            $instance = $attribute->newInstance();

            return $instance;
        }

        return null;
    }

    /**
     * Highlights a JSON structure using HTML span tags with colors.
     *
     * @param array $value The JSON data to be highlighted.
     *
     * @throws \JsonException If encoding fails.
     *
     * @return \Illuminate\Support\Stringable The highlighted JSON as a string.
     */
    private function highlight(array $value): Stringable
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return Str::of($json)
            // Highlight keys (both string and numeric)
            ->replaceMatches('/"(\w+)":/i', '"<span style="color:#A0AEC0;">$1</span>":')
            ->replaceMatches('/"(\d+)":/i', '"<span style="color:#A0AEC0;">$1</span>":')

            // Highlight null values
            ->replaceMatches('/":\s*(null)/i', '": <span style="color:#F7768E;">$1</span>')

            // Highlight string values
            ->replaceMatches('/":\s*"([^"]*)"/', '": "<span style="color:#9ECE6A;">$1</span>"')

            // Highlight numeric values
            ->replaceMatches('/":\s*(\d+(\.\d+)?)/', '": <span style="color:#E0AF68;">$1</span>')

            // Highlight boolean values (true/false)
            ->replaceMatches('/":\s*(true|false)/i', '": <span style="color:#7AA2F7;">$1</span>')

            ->wrap('<pre style="color:rgba(212,212,212,0.75);">', '</pre>');
    }
}
