<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Concerns;

use Kopling\Core\Extension\Contract\ValidatesModels;

trait AggregatesModelValidation
{
    /**
     * @return array<class-string, array{rules: array<string, mixed>, messages: array<string, string>}>
     */
    public function modelValidationRules(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return $cached['modelValidations'];
        }

        $declared = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ValidatesModels) {
                continue;
            }

            foreach ($extension->modelValidationRules() as $class => $definition) {
                $existing = $declared[$class] ?? ['rules' => [], 'messages' => []];

                $declared[$class] = [
                    'rules' => array_merge($existing['rules'], $definition['rules'] ?? []),
                    'messages' => array_merge($existing['messages'], $definition['messages'] ?? []),
                ];
            }
        }

        return $declared;
    }

    /**
     * Merges a controller's own base rules/messages for `$modelClass` with whatever
     * `modelValidationRules()` aggregated for it.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    public function mergeModelValidationRules(string $modelClass, array $rules, array $messages = []): array
    {
        $extra = $this->modelValidationRules()[$modelClass] ?? ['rules' => [], 'messages' => []];

        return [
            'rules' => array_merge($rules, $extra['rules']),
            'messages' => array_merge($messages, $extra['messages']),
        ];
    }
}
