<?php

namespace Tests\Feature\Equipo4;

use App\Services\IdentityService;
use Tests\TestCase;

/**
 * Verifica el comportamiento multi-rol (Opcion B).
 *
 * Un subject en Eq.1 puede tener varios roles business. Eq.4:
 *   - Devuelve 'roles' como array
 *   - 'role' es solo el primario (mayor prioridad) para UI
 *   - userCan() evalua la UNION de permisos
 */
class MultiRoleTest extends TestCase
{
    protected IdentityService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(IdentityService::class);
    }

    // =================================================================
    // Multi-rol CON admin (admin tiene '*', cubre todo)
    // =================================================================

    /** @test */
    public function test_usuario_con_admin_e_inventory_manager_devuelve_ambos_roles(): void
    {
        $user = $this->svc->getUser('USR-MULTI-001');
        $this->assertNotNull($user);
        $this->assertIsArray($user['roles']);
        $this->assertContains('admin', $user['roles']);
        $this->assertContains('inventory_manager', $user['roles']);
        $this->assertCount(2, $user['roles']);
    }

    /** @test */
    public function test_rol_primario_es_admin_para_multi_rol_con_admin(): void
    {
        $user = $this->svc->getUser('USR-MULTI-001');
        $this->assertSame('admin', $user['role']);
    }

    /** @test */
    public function test_multi_rol_con_admin_puede_permisos_de_admin(): void
    {
        // productos.delete es exclusivo de admin
        $this->assertTrue($this->svc->userCan('USR-MULTI-001', 'productos.delete'));
    }

    /** @test */
    public function test_multi_rol_con_admin_puede_permisos_de_inventory_manager(): void
    {
        $this->assertTrue($this->svc->userCan('USR-MULTI-001', 'inventario.adjust'));
        $this->assertTrue($this->svc->userCan('USR-MULTI-001', 'conteos.close'));
    }

    // =================================================================
    // Multi-rol SIN admin (union REAL de permisos, sin '*')
    // =================================================================

    /** @test */
    public function test_multi_rol_sin_admin_devuelve_ambos_roles(): void
    {
        $user = $this->svc->getUser('USR-MULTI2-001');
        $this->assertNotNull($user);
        $this->assertIsArray($user['roles']);
        $this->assertContains('inventory_manager', $user['roles']);
        $this->assertContains('buyer', $user['roles']);
        $this->assertCount(2, $user['roles']);
    }

    /** @test */
    public function test_multi_rol_sin_admin_hereda_permisos_de_inventory_manager(): void
    {
        // inventario.adjust y conteos.close son de inventory_manager
        $this->assertTrue($this->svc->userCan('USR-MULTI2-001', 'inventario.adjust'));
        $this->assertTrue($this->svc->userCan('USR-MULTI2-001', 'conteos.close'));
    }

    /** @test */
    public function test_multi_rol_sin_admin_hereda_permisos_de_buyer(): void
    {
        // proveedores.delete y recepciones.create son de buyer
        $this->assertTrue($this->svc->userCan('USR-MULTI2-001', 'proveedores.delete'));
        $this->assertTrue($this->svc->userCan('USR-MULTI2-001', 'recepciones.create'));
    }

    /** @test */
    public function test_multi_rol_sin_admin_no_obtiene_permisos_que_no_tiene_ninguno(): void
    {
        // productos.delete: ni inventory_manager ni buyer lo tienen
        $this->assertFalse($this->svc->userCan('USR-MULTI2-001', 'productos.delete'));
    }

    // =================================================================
    // Control: usuarios de UN solo rol NO heredan permisos ajenos
    // =================================================================

    /** @test */
    public function test_inventory_manager_solo_no_puede_borrar_productos(): void
    {
        $this->assertFalse($this->svc->userCan('USR-INV-001', 'productos.delete'));
    }

    /** @test */
    public function test_buyer_solo_no_puede_ajustar_inventario(): void
    {
        $this->assertFalse($this->svc->userCan('USR-BUY-001', 'inventario.adjust'));
    }

    /** @test */
    public function test_buyer_solo_no_puede_cerrar_conteos(): void
    {
        $this->assertFalse($this->svc->userCan('USR-BUY-001', 'conteos.close'));
    }

    /** @test */
    public function test_admin_solo_puede_todos_los_permisos(): void
    {
        $this->assertTrue($this->svc->userCan('USR-ADMIN-001', 'productos.delete'));
        $this->assertTrue($this->svc->userCan('USR-ADMIN-001', 'inventario.adjust'));
        $this->assertTrue($this->svc->userCan('USR-ADMIN-001', 'proveedores.delete'));
    }

    // =================================================================
    // isAuthorized + getPermissions + getRoles con array
    // =================================================================

    /** @test */
    public function test_is_authorized_con_array_de_roles(): void
    {
        $this->assertTrue($this->svc->isAuthorized(['admin', 'inventory_manager']));
        $this->assertTrue($this->svc->isAuthorized(['admin']));
        $this->assertTrue($this->svc->isAuthorized(['inventory_manager', 'buyer']));
        $this->assertFalse($this->svc->isAuthorized(['unknown_role']));
        $this->assertFalse($this->svc->isAuthorized([]));
        $this->assertFalse($this->svc->isAuthorized(null));
    }

    /** @test */
    public function test_get_permissions_acepta_array_de_roles(): void
    {
        // Con admin -> '*'
        $perms = $this->svc->getPermissions(['admin', 'inventory_manager']);
        $this->assertContains('*', $perms);

        // Sin admin -> union sin '*'
        $perms = $this->svc->getPermissions(['inventory_manager', 'buyer']);
        $this->assertNotContains('*', $perms);
        $this->assertContains('inventario.adjust', $perms);
        $this->assertContains('proveedores.delete', $perms);
    }

    /** @test */
    public function test_get_roles_devuelve_array_completo(): void
    {
        $roles = $this->svc->getRoles('USR-MULTI-001');
        $this->assertIsArray($roles);
        $this->assertCount(2, $roles);

        $roles = $this->svc->getRoles('USR-ADMIN-001');
        $this->assertCount(1, $roles);
    }
}