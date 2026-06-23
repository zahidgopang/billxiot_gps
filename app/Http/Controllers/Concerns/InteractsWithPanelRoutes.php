<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait InteractsWithPanelRoutes
{
    protected function isClientPanel(?Request $request = null): bool
    {
        $request ??= request();

        return $request->routeIs('client.*');
    }

    protected function panelPrefix(?Request $request = null): string
    {
        return $this->isClientPanel($request) ? 'client' : 'admin';
    }

    protected function panelRoute(string $name, mixed $parameters = []): string
    {
        return route($this->panelPrefix() . '.' . $name, $parameters);
    }

    /**
     * @return array{prefix: string, isClientPanel: bool}
     */
    protected function panelViewData(?Request $request = null): array
    {
        return [
            'panelPrefix' => $this->panelPrefix($request),
            'isClientPanel' => $this->isClientPanel($request),
        ];
    }
}
