<?php

namespace App\Providers;

use App\Models\AjusteAuditoria;
use App\Models\AuditoriaInventario;
use App\Models\Cliente;
use App\Models\Contratista;
use App\Models\Equipo;
use App\Models\Inventario;
use App\Models\Proveedor;
use App\Models\Tecnico;
use App\Models\User;
use App\Policies\AuditoriaPolicy;
use App\Policies\ClientePolicy;
use App\Policies\ContratistaPolicy;
use App\Policies\EquipoPolicy;
use App\Policies\InventarioPolicy;
use App\Policies\ProveedorPolicy;
use App\Policies\TecnicoPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Registro explícito de Policies del sistema (spec 001, T031).
 *
 * Laravel resuelve Policies por convención de nombre (App\Models\X -> App\Policies\XPolicy)
 * sin necesidad de este mapa, pero se documenta aquí para que sea el punto único y visible
 * donde cada spec nueva (002 OrdenTrabajo, 003 Inventario, etc.) registra su Policy al
 * introducir un modelo con reglas de autorización — patrón a seguir por los demás módulos.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Cliente::class => ClientePolicy::class,
        Proveedor::class => ProveedorPolicy::class,
        Contratista::class => ContratistaPolicy::class,
        Tecnico::class => TecnicoPolicy::class,
        Equipo::class => EquipoPolicy::class,
        Inventario::class => InventarioPolicy::class,
        AuditoriaInventario::class => AuditoriaPolicy::class,
        AjusteAuditoria::class => AuditoriaPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
