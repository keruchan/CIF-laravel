<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    public function test_existing_portals_render_and_load_the_original_assets(): void
    {
        foreach (['admin', 'mrf-requestor', 'superadmin', 'candidate'] as $role) {
            $this->get('/pages/'.$role.'/index.php')->assertOk()->assertSee('APEC Homes')->assertSee('session-loading');
        }
        $this->get('/pages/portal/index.php')->assertOk()->assertSee('HR Recruitment')->assertSee('MRF Requestor')->assertDontSee('Superadmin');
        foreach (['/', '/index.php', '/index.html'] as $entry) {
            $this->get($entry)->assertRedirect('/pages/candidate/');
        }
    }

    public function test_session_state_uses_the_laravel_csrf_token_without_exposing_credentials(): void
    {
        $this->getJson('/ajax/access.php?action=state')->assertOk()
            ->assertJson(['admin' => null, 'candidate' => null])->assertJsonStructure(['csrf']);
        $this->getJson('/ajax/department.php?action=state')->assertOk()->assertJson(['department' => null]);
    }

    public function test_private_print_and_record_routes_require_the_correct_role(): void
    {
        foreach (['admin/record', 'admin/mrf', 'mrf-requestor/mrf'] as $page) {
            $this->get('/pages/'.$page.'.php?id=1')->assertUnauthorized();
        }
        $this->get('/pages/mrf-requestor/mrf-form.php')->assertRedirect('/pages/mrf-requestor/#mrf');
        $this->get('/pages/department/mrf.php?id=1&print=1')->assertStatus(302)->assertHeader('Location', '/pages/mrf-requestor/mrf.php?id=1&print=1');
        $this->postJson('/ajax/access.php?action=candidates')->assertUnauthorized();
        $this->postJson('/ajax/mrf-requestors.php?action=list')->assertUnauthorized();
        $this->postJson('/ajax/email.php?action=templates')->assertUnauthorized();
        $this->get('/ajax/mrf-import.php?action=download&id=1')->assertUnauthorized();
    }

    public function test_portal_redirects_preserve_directory_slashes_and_query_strings(): void
    {
        foreach (['admin', 'candidate', 'portal', 'mrf-requestor', 'superadmin'] as $portal) {
            $this->get('/'.$portal)->assertStatus(302)->assertHeader('Location', '/pages/'.$portal.'/');
            $this->get('/pages/'.$portal.'?from=bookmark')->assertStatus(301)->assertHeader('Location', '/pages/'.$portal.'/?from=bookmark');
        }
        $this->get('/pages/department/?source=old-link')->assertStatus(302)->assertHeader('Location', '/pages/mrf-requestor/?source=old-link');
    }
}
