<?php

namespace App\Enums;

/**
 * Default roles. Super Admin is platform-level (tenant_id null); the rest
 * are provisioned per tenant. Payroll Admin is intentionally omitted — it is
 * an optional custom role a tenant may compose from permissions.
 */
enum Role: string
{
    case SuperAdmin = 'Super Admin';
    case CompanyAdmin = 'Company Admin';
    case HrAdmin = 'HR Admin';
    case ApproverFinance = 'Approver/Finance';
    case Manager = 'Manager';
    case Employee = 'Employee';

    /**
     * Default roles provisioned for every tenant.
     *
     * @return list<self>
     */
    public static function tenantRoles(): array
    {
        return [
            self::CompanyAdmin,
            self::HrAdmin,
            self::ApproverFinance,
            self::Manager,
            self::Employee,
        ];
    }

    /**
     * Permissions granted to this role (dotted permission strings).
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [
                Permission::PlatformTenantsManage->value,
                Permission::PlatformPlansManage->value,
                Permission::PlatformMenusManage->value,
                Permission::PlatformAiManage->value,
            ],
            self::CompanyAdmin => Permission::tenantValues(),
            self::HrAdmin => [
                Permission::OrganizationView->value, Permission::OrganizationManage->value,
                Permission::EmployeesView->value, Permission::EmployeesCreate->value,
                Permission::EmployeesUpdate->value, Permission::EmployeesDelete->value,
                Permission::EmployeesExport->value, Permission::EmployeesApproveChanges->value,
                Permission::BranchesView->value, Permission::BranchesManage->value,
                Permission::AttendanceView->value, Permission::AttendanceManage->value,
                Permission::AttendanceApproveCorrection->value, Permission::AttendanceLock->value,
                Permission::LeaveView->value, Permission::LeaveApprove->value, Permission::LeaveManageTypes->value,
                Permission::OvertimeView->value, Permission::OvertimeApprove->value,
                Permission::ShiftView->value, Permission::ShiftManage->value,
                Permission::PayrollView->value, Permission::PayrollManageMaster->value,
                Permission::PayrollProcess->value, Permission::PayrollViewAllPayslips->value,
                Permission::PayrollAdjust->value,
                Permission::ApprovalManageFlows->value, Permission::ApprovalAct->value,
                Permission::AuditView->value, Permission::RolesView->value, Permission::MenuManage->value,
                Permission::CalendarView->value, Permission::CalendarManage->value,
                Permission::AnnouncementView->value, Permission::AnnouncementManage->value,
                Permission::DashboardView->value, Permission::ReportsView->value,
                Permission::MssAccess->value,
            ],
            self::ApproverFinance => [
                Permission::PayrollView->value, Permission::PayrollApprove->value,
                Permission::PayrollLock->value, Permission::PayrollExportBank->value,
                Permission::PayrollViewAllPayslips->value,
                Permission::ApprovalAct->value,
                Permission::DashboardView->value, Permission::ReportsView->value,
            ],
            self::Manager => [
                Permission::EmployeesView->value,
                Permission::AttendanceView->value, Permission::AttendanceApproveCorrection->value,
                Permission::LeaveView->value, Permission::LeaveApprove->value,
                Permission::OvertimeView->value, Permission::OvertimeApprove->value,
                Permission::ApprovalAct->value,
                Permission::CalendarView->value, Permission::AnnouncementView->value,
                Permission::DashboardView->value, Permission::MssAccess->value,
            ],
            self::Employee => [
                Permission::LeaveRequest->value, Permission::OvertimeRequest->value,
                Permission::AttendanceCorrect->value, Permission::PayrollView->value,
                Permission::CalendarView->value, Permission::AnnouncementView->value,
                Permission::DashboardView->value, Permission::EssAccess->value,
            ],
        };
    }
}
