<?php

namespace Libinkk\Permission\Debug;

/**
 * DebugBar collector. Registered only when DebugBar is bound; not a Composer dependency.
 */
class AuthorizationCollector
{
    public function __construct(
        protected DecisionRecorder $recorder,
    ) {
    }

    public function getName(): string
    {
        return 'libinkk_permission';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $entries = $this->recorder->all();

        return [
            'count' => count($entries),
            'decisions' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgets(): array
    {
        return [
            'libinkk_permission' => [
                'icon' => 'lock',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'libinkk_permission',
                'default' => '{}',
            ],
            'libinkk_permission:badge' => [
                'map' => 'libinkk_permission.count',
                'default' => 0,
            ],
        ];
    }
}
