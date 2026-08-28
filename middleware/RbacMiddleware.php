<?php
/**
 * Role-Based Access Control helpers
 */
require_once __DIR__ . '/SecurityMiddleware.php';

class RbacMiddleware
{
    /** Employer-side roles that can manage HR/payroll */
    public const HR_ROLES = ['admin', 'hr', 'employer'];

    /** Roles that can approve leave */
    public const LEAVE_APPROVER_ROLES = ['admin', 'hr', 'employer', 'manager'];

    public static function role(array $session): string
    {
        return strtolower($session['role'] ?? $session['user_type'] ?? 'employee');
    }

    public static function userType(array $session): string
    {
        return strtolower($session['user_type'] ?? 'employee');
    }

    public static function isEmployerUser(array $session): bool
    {
        return self::userType($session) === 'employer';
    }

    public static function isEmployeeUser(array $session): bool
    {
        return self::userType($session) === 'employee';
    }

    public static function isHrAdmin(array $session): bool
    {
        if (self::isEmployerUser($session)) {
            return in_array(self::role($session), self::HR_ROLES, true);
        }
        return in_array(self::role($session), ['admin', 'hr'], true);
    }

    public static function isManager(array $session): bool
    {
        return self::role($session) === 'manager';
    }

    public static function requireAuth(): array
    {
        return SecurityMiddleware::verifyToken();
    }

    public static function requireHrAdmin(?array $session = null): array
    {
        $session = $session ?? self::requireAuth();
        if (!self::isHrAdmin($session)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'HR/Admin access required']);
            exit();
        }
        return $session;
    }

    public static function requireEmployer(?array $session = null): array
    {
        $session = $session ?? self::requireAuth();
        if (!self::isEmployerUser($session)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Employer access required']);
            exit();
        }
        return $session;
    }

    public static function requireAnyRole(array $roles, ?array $session = null): array
    {
        $session = $session ?? self::requireAuth();
        $role = self::role($session);
        $userType = self::userType($session);

        $effectiveRoles = [$role];
        if ($userType === 'employer' && in_array('employer', $roles, true)) {
            $effectiveRoles[] = 'employer';
        }

        $allowed = array_map('strtolower', $roles);
        foreach ($effectiveRoles as $r) {
            if (in_array(strtolower($r), $allowed, true)) {
                return $session;
            }
        }

        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied for your role']);
        exit();
    }

    public static function organizationId(array $session): int
    {
        return (int)($session['organization_id'] ?? 0);
    }

    public static function employeeId(array $session): int
    {
        return (int)($session['employee_id'] ?? 0);
    }

    public static function canViewEmployee(PDO $db, array $session, int $targetEmployeeId): bool
    {
        if ($targetEmployeeId <= 0) {
            return false;
        }

        if (self::isHrAdmin($session)) {
            $orgId = self::organizationId($session);
            $stmt = $db->prepare("SELECT id FROM employees WHERE id = :id AND organization_id = :org LIMIT 1");
            $stmt->execute([':id' => $targetEmployeeId, ':org' => $orgId]);
            return (bool)$stmt->fetch();
        }

        if (self::isEmployeeUser($session)) {
            $myId = self::employeeId($session);
            if ($myId === $targetEmployeeId) {
                return true;
            }
            if (self::isManager($session)) {
                $stmt = $db->prepare("SELECT id FROM employees WHERE id = :id AND manager_id = :mgr LIMIT 1");
                $stmt->execute([':id' => $targetEmployeeId, ':mgr' => $myId]);
                return (bool)$stmt->fetch();
            }
        }

        return false;
    }

    public static function requireEmployeeAccess(PDO $db, array $session, int $targetEmployeeId): void
    {
        if (!self::canViewEmployee($db, $session, $targetEmployeeId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied to this employee record']);
            exit();
        }
    }
}
