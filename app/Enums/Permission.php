<?php

namespace App\Enums;

/**
 * Granular permissions per module (Wave 1 MVP).
 *
 * Values are the dotted strings passed to `can()` / spatie. Grouped by
 * module; `platform.*` are Super Admin only, the rest are tenant-scoped.
 */
enum Permission: string
{
    // Platform (Super Admin)
    case PlatformTenantsManage = 'platform.tenants.manage';
    case PlatformPlansManage = 'platform.plans.manage';
    case PlatformMenusManage = 'platform.menus.manage';
    case PlatformAiManage = 'platform.ai.manage';

    // Organisasi
    case OrganizationView = 'organization.view';
    case OrganizationManage = 'organization.manage';

    // Karyawan
    case EmployeesView = 'employees.view';
    case EmployeesCreate = 'employees.create';
    case EmployeesUpdate = 'employees.update';
    case EmployeesDelete = 'employees.delete';
    case EmployeesExport = 'employees.export';
    case EmployeesApproveChanges = 'employees.approve-changes';

    // Cabang
    case BranchesView = 'branches.view';
    case BranchesManage = 'branches.manage';

    // Kehadiran
    case AttendanceView = 'attendance.view';
    case AttendanceManage = 'attendance.manage';
    case AttendanceCorrect = 'attendance.correct';
    case AttendanceApproveCorrection = 'attendance.approve-correction';
    case AttendanceLock = 'attendance.lock';

    // Cuti
    case LeaveView = 'leave.view';
    case LeaveRequest = 'leave.request';
    case LeaveApprove = 'leave.approve';
    case LeaveManageTypes = 'leave.manage-types';

    // Lembur
    case OvertimeView = 'overtime.view';
    case OvertimeRequest = 'overtime.request';
    case OvertimeApprove = 'overtime.approve';

    // Shift & Jadwal
    case ShiftView = 'shift.view';
    case ShiftManage = 'shift.manage';

    // Payroll
    case PayrollView = 'payroll.view';
    case PayrollManageMaster = 'payroll.manage-master';
    case PayrollProcess = 'payroll.process';
    case PayrollApprove = 'payroll.approve';
    case PayrollLock = 'payroll.lock';
    case PayrollViewAllPayslips = 'payroll.view-all-payslips';
    case PayrollAdjust = 'payroll.adjust';
    case PayrollExportBank = 'payroll.export-bank';

    // Approval engine
    case ApprovalManageFlows = 'approval.manage-flows';
    case ApprovalAct = 'approval.act';

    // Platform-lintas tenant
    case AuditView = 'audit.view';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case MenuManage = 'menu.manage';

    // CRM
    case CrmView = 'crm.view';
    case CrmManage = 'crm.manage';
    case CrmManagePipeline = 'crm.manage-pipeline';
    case CrmViewAll = 'crm.view-all';

    // Kalender & pengumuman
    case CalendarView = 'calendar.view';
    case CalendarManage = 'calendar.manage';
    case AnnouncementView = 'announcement.view';
    case AnnouncementManage = 'announcement.manage';

    // Umum
    case DashboardView = 'dashboard.view';
    case ReportsView = 'reports.view';
    case EssAccess = 'ess.access';
    case MssAccess = 'mss.access';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }

    /**
     * Tenant-scoped permissions (everything except platform.*).
     *
     * @return list<string>
     */
    public static function tenantValues(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $v): bool => ! str_starts_with($v, 'platform.'),
        ));
    }
}
