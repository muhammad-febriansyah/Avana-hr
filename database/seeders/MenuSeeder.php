<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform menu registry (Wave 1). New menus in later releases are
 * added here rather than by editing the sidebar component. Children reference
 * their parent by `code`; nesting is capped at 2 levels.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->menus() as $order => $menu) {
            $parentId = isset($menu['parent'])
                ? Menu::where('code', $menu['parent'])->value('id')
                : null;

            Menu::updateOrCreate(
                ['code' => $menu['code']],
                [
                    'parent_id' => $parentId,
                    'label_default' => $menu['label'],
                    'icon' => $menu['icon'] ?? null,
                    'route_name' => $menu['route'] ?? null,
                    'permission_code' => $menu['permission'] ?? null,
                    'feature_code' => $menu['feature'] ?? null,
                    'sort_order' => $menu['sort'] ?? ($order + 1),
                    'is_core' => $menu['core'] ?? false,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Registry definition. Parents are listed before their children so the
     * parent id resolves during the single pass.
     *
     * @return list<array<string, mixed>>
     */
    private function menus(): array
    {
        return [
            // Top level
            ['code' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'LayoutGrid', 'route' => 'dashboard', 'permission' => 'dashboard.view', 'core' => true, 'sort' => 1],
            ['code' => 'employees', 'label' => 'Karyawan', 'icon' => 'Users', 'permission' => 'employees.view', 'sort' => 2],
            ['code' => 'organization', 'label' => 'Struktur Organisasi', 'icon' => 'Network', 'route' => 'organization.index', 'permission' => 'organization.view', 'sort' => 3],
            ['code' => 'attendance', 'label' => 'Kehadiran', 'icon' => 'Clock', 'permission' => 'attendance.view', 'sort' => 4],
            ['code' => 'leave', 'label' => 'Cuti', 'icon' => 'CalendarCheck', 'permission' => 'leave.view', 'sort' => 5],
            ['code' => 'payroll', 'label' => 'Payroll', 'icon' => 'Wallet', 'permission' => 'payroll.view', 'sort' => 6],
            ['code' => 'approvals', 'label' => 'Persetujuan Saya', 'icon' => 'Inbox', 'route' => 'approvals.index', 'permission' => 'approval.act', 'sort' => 7],
            ['code' => 'approval-workflow', 'label' => 'Alur Persetujuan', 'icon' => 'GitBranch', 'route' => 'approval-workflow.index', 'permission' => 'approval.manage-flows', 'sort' => 8],
            ['code' => 'crm', 'label' => 'CRM', 'icon' => 'Contact', 'permission' => 'crm.view', 'feature' => 'crm', 'sort' => 9],
            ['code' => 'calendar', 'label' => 'Kalender', 'icon' => 'Calendar', 'permission' => 'calendar.view', 'feature' => 'calendar', 'sort' => 10],
            ['code' => 'reports', 'label' => 'Laporan', 'icon' => 'BarChart3', 'permission' => 'reports.view', 'sort' => 11],
            ['code' => 'roles', 'label' => 'Peran & Akses', 'icon' => 'ShieldCheck', 'route' => 'roles.index', 'permission' => 'roles.view', 'sort' => 12],
            ['code' => 'audit', 'label' => 'Audit Log', 'icon' => 'ScrollText', 'route' => 'audit.index', 'permission' => 'audit.view', 'sort' => 13],
            ['code' => 'menu-settings', 'label' => 'Pengaturan Menu', 'icon' => 'SlidersHorizontal', 'route' => 'settings.menus.index', 'permission' => 'menu.manage', 'sort' => 14],
            ['code' => 'settings', 'label' => 'Pengaturan', 'icon' => 'Settings', 'route' => 'profile.edit', 'core' => true, 'sort' => 15],

            // Attendance submenu
            ['code' => 'attendance.monitoring', 'parent' => 'attendance', 'label' => 'Monitoring Kehadiran', 'permission' => 'attendance.view', 'sort' => 1],
            ['code' => 'attendance.correction', 'parent' => 'attendance', 'label' => 'Koreksi Kehadiran', 'permission' => 'attendance.correct', 'sort' => 2],

            // Payroll submenu
            ['code' => 'payroll.process', 'parent' => 'payroll', 'label' => 'Proses Payroll', 'permission' => 'payroll.process', 'sort' => 1],
            ['code' => 'payroll.payslip', 'parent' => 'payroll', 'label' => 'Slip Gaji', 'permission' => 'payroll.view', 'sort' => 2],
            ['code' => 'payroll.bank', 'parent' => 'payroll', 'label' => 'Bank File', 'permission' => 'payroll.export-bank', 'sort' => 3],
        ];
    }
}
