<?php
/**
 * Front controller — all requests route here (Foundation §1).
 * Middleware chain runs before the controller, so authorisation is enforced first.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\Auth;
use App\Helpers\Config;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\PasswordResetController;
use App\Controllers\DashboardController;
use App\Controllers\DepartmentController;
use App\Controllers\GeographyController;
use App\Controllers\LookupController;
use App\Controllers\OptionListController;
use App\Controllers\OnboardingController;
use App\Controllers\EnrolmentController;
use App\Controllers\StudentFormController;
use App\Controllers\ApprovalController;
use App\Controllers\RtcController;
use App\Controllers\NotificationController;
use App\Controllers\StaffController;
use App\Controllers\StaffSelfController;
use App\Controllers\FieldConfigController;
use App\Controllers\CustomFieldController;
use App\Controllers\StudentGridController;
use App\Controllers\PromotionController;

Config::setPath(dirname(__DIR__) . '/config');
Auth::start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$base   = Config::get('app.base_url', '');
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($base && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base)) ?: '/';
}

/**
 * Match a URI against a pattern that may contain {param} placeholders.
 * On match, populates $params with captured values and returns true.
 */
function matchRoute(string $pattern, string $uri, array &$params): bool
{
    $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (preg_match($regex, $uri, $m)) {
        preg_match_all('/\{(\w+)\}/', $pattern, $names);
        foreach ($names[1] as $i => $name) {
            $params[$name] = $m[$i + 1];
        }
        return true;
    }
    return false;
}

// route => [ METHOD, path, handler, middleware[] ]
$routes = [
    // --- Module 1: Auth ---
    ['GET',  '/',                 fn() => View::redirect(Auth::check() ? '/dashboard' : '/login'), []],
    ['GET',  '/login',            [AuthController::class, 'showLogin'],      []],
    ['POST', '/login/student',    [AuthController::class, 'studentLogin'],   []],
    ['POST', '/login/staff',      [AuthController::class, 'staffLogin'],     []],
    ['GET',  '/login/otp',        [AuthController::class, 'showOtp'],        []],
    ['POST', '/login/otp',        [AuthController::class, 'verifyOtp'],      []],
    ['POST', '/logout',           [AuthController::class, 'logout'],         ['auth']],
    ['GET',  '/forgot-password',  [PasswordResetController::class, 'showForgot'],   []],
    ['POST', '/forgot-password',  [PasswordResetController::class, 'sendReset'],    []],
    ['GET',  '/reset-password',   [PasswordResetController::class, 'showReset'],    []],
    ['POST', '/reset-password',   [PasswordResetController::class, 'submitReset'],  []],
    ['GET',  '/dashboard',        [DashboardController::class, 'index'],     ['auth']],

    // --- Module 2: Master Data — Departments ---
    ['GET',  '/master-data/departments',                         [DepartmentController::class, 'index'],      ['auth']],
    ['GET',  '/master-data/departments/create',                  [DepartmentController::class, 'create'],     ['auth']],
    ['POST', '/master-data/departments',                         [DepartmentController::class, 'store'],      ['auth']],
    ['GET',  '/master-data/departments/{id}/edit',               [DepartmentController::class, 'edit'],       ['auth']],
    ['POST', '/master-data/departments/{id}',                    [DepartmentController::class, 'update'],     ['auth']],
    ['POST', '/master-data/departments/{id}/deactivate',         [DepartmentController::class, 'deactivate'], ['auth']],
    ['POST', '/master-data/departments/{id}/reactivate',         [DepartmentController::class, 'reactivate'], ['auth']],

    // --- Module 2: Master Data — Geography ---
    ['GET',  '/master-data/geography',                           [GeographyController::class, 'index'],           ['auth']],
    ['POST', '/master-data/geography/states',                    [GeographyController::class, 'storeState'],      ['auth']],
    ['POST', '/master-data/geography/states/{id}',               [GeographyController::class, 'updateState'],     ['auth']],
    ['POST', '/master-data/geography/states/{id}/deactivate',    [GeographyController::class, 'deactivateState'], ['auth']],
    ['POST', '/master-data/geography/states/{id}/reactivate',    [GeographyController::class, 'reactivateState'], ['auth']],
    ['POST', '/master-data/geography/districts',                 [GeographyController::class, 'storeDistrict'],   ['auth']],
    ['POST', '/master-data/geography/districts/{id}',            [GeographyController::class, 'updateDistrict'],  ['auth']],
    ['POST', '/master-data/geography/districts/{id}/deactivate', [GeographyController::class, 'deactivateDistrict'], ['auth']],
    ['POST', '/master-data/geography/taluks',                    [GeographyController::class, 'storeTaluk'],      ['auth']],
    ['POST', '/master-data/geography/taluks/{id}',               [GeographyController::class, 'updateTaluk'],     ['auth']],
    ['POST', '/master-data/geography/taluks/{id}/deactivate',    [GeographyController::class, 'deactivateTaluk'], ['auth']],
    ['GET',  '/master-data/geography/import',                    [GeographyController::class, 'import'],          ['auth']],
    ['POST', '/master-data/geography/import',                    [GeographyController::class, 'import'],          ['auth']],

    // --- Module 2: Lookup (AJAX) ---
    ['GET',  '/lookup/districts',                                [LookupController::class, 'districts'], ['auth']],
    ['GET',  '/lookup/taluks',                                   [LookupController::class, 'taluks'],    ['auth']],

    // --- Module 2: Master Data — Option Lists ---
    ['GET',  '/master-data/option-lists',                                         [OptionListController::class, 'index'],         ['auth']],
    ['GET',  '/master-data/option-lists/{id}',                                    [OptionListController::class, 'show'],          ['auth']],
    ['POST', '/master-data/option-lists/{id}/values',                             [OptionListController::class, 'storeValue'],    ['auth']],
    ['POST', '/master-data/option-lists/{id}/values/{vid}/edit',                  [OptionListController::class, 'updateValue'],   ['auth']],
    ['POST', '/master-data/option-lists/{id}/values/{vid}/deactivate',            [OptionListController::class, 'deactivateValue'], ['auth']],
    ['POST', '/master-data/option-lists/{id}/values/{vid}/reactivate',            [OptionListController::class, 'reactivateValue'], ['auth']],

    // --- Module 3: Student Onboarding ---
    ['GET',  '/onboarding',                         [OnboardingController::class, 'index'],            ['auth']],
    ['GET',  '/onboarding/template',                [OnboardingController::class, 'downloadTemplate'], ['auth']],
    ['GET',  '/onboarding/upload',                  [OnboardingController::class, 'showUpload'],       ['auth']],
    ['POST', '/onboarding/upload',                  [OnboardingController::class, 'upload'],           ['auth']],
    ['GET',  '/onboarding/result/{id}',             [OnboardingController::class, 'result'],           ['auth']],
    ['GET',  '/onboarding/result/{id}/errors.xlsx', [OnboardingController::class, 'downloadErrors'],   ['auth']],
    ['GET',  '/onboarding/duplicates/{id}',         [OnboardingController::class, 'reviewDuplicates'], ['auth']],
    ['POST', '/onboarding/duplicates/{id}',         [OnboardingController::class, 'resolveDuplicates'],['auth']],
    ['GET',  '/onboarding/overrides',               [OnboardingController::class, 'pendingOverrides'], ['auth']],
    ['POST', '/onboarding/overrides/{id}/approve',  [OnboardingController::class, 'approveOverride'],  ['auth']],
    ['POST', '/onboarding/overrides/{id}/reject',   [OnboardingController::class, 'rejectOverride'],   ['auth']],
    ['GET',  '/onboarding/add',                     [OnboardingController::class, 'showAdd'],          ['auth']],
    ['POST', '/onboarding/add',                     [OnboardingController::class, 'store'],            ['auth']],
    ['GET',  '/onboarding/summary',                 [OnboardingController::class, 'summary'],          ['auth']],

    // --- Module 4: Enrolment Numbers ---
    ['GET',  '/enrolment',                             [EnrolmentController::class, 'index'],          ['auth']],
    ['GET',  '/enrolment/generate',                    [EnrolmentController::class, 'generateForm'],   ['auth']],
    ['POST', '/enrolment/generate',                    [EnrolmentController::class, 'generate'],       ['auth']],
    ['GET',  '/enrolment/eligible-count',              [EnrolmentController::class, 'eligibleCount'],  ['auth']],
    ['GET',  '/enrolment/summary',                     [EnrolmentController::class, 'summary'],        ['auth']],
    ['GET',  '/enrolment/batch/{id}',                  [EnrolmentController::class, 'batchDetail'],    ['auth']],
    ['POST', '/enrolment/batch/{id}/approve-all',      [EnrolmentController::class, 'approveAll'],     ['auth']],
    ['POST', '/enrolment/batch/{id}/approve-selected', [EnrolmentController::class, 'approveSelected'],['auth']],

    // --- Module 12: Student Promotion (static paths before {id} wildcards) ---
    ['POST', '/promotion/window/toggle',  [PromotionController::class, 'toggleWindow'], ['auth']],
    ['GET',  '/promotion/create',         [PromotionController::class, 'createForm'],   ['auth']],
    ['POST', '/promotion/create',         [PromotionController::class, 'store'],        ['auth']],
    ['GET',  '/promotion/{id}/edit',      [PromotionController::class, 'editForm'],     ['auth']],
    ['POST', '/promotion/{id}/edit',      [PromotionController::class, 'update'],       ['auth']],
    ['POST', '/promotion/{id}/approve',   [PromotionController::class, 'approve'],      ['auth']],
    ['POST', '/promotion/{id}/reject',    [PromotionController::class, 'reject'],       ['auth']],
    ['GET',  '/promotion/{id}',           [PromotionController::class, 'detail'],       ['auth']],
    ['GET',  '/promotion',                [PromotionController::class, 'index'],        ['auth']],

    // --- Module 11: Student Data Grid & Export (static paths before wildcard) ---
    ['GET', '/students/export', [StudentGridController::class, 'export'], ['auth']],
    ['GET', '/students',        [StudentGridController::class, 'index'],  ['auth']],

    // --- Module 5: Student Information Form ---
    ['GET',  '/student/form',                      [StudentFormController::class, 'show'],      ['auth']],
    ['POST', '/student/form/save',                 [StudentFormController::class, 'save'],      ['auth']],
    ['POST', '/student/form/submit',               [StudentFormController::class, 'submit'],    ['auth']],
    ['GET',  '/student/form/view',                 [StudentFormController::class, 'view'],      ['auth']],
    ['GET',  '/student/form/{studentId}/view',     [StudentFormController::class, 'staffView'], ['auth']],

    // --- Module 6: Submission Approval & RTC ---
    ['GET',  '/approvals',                        [ApprovalController::class, 'index'],             ['auth']],
    ['POST', '/approvals/{studentId}/approve',    [ApprovalController::class, 'approveSubmission'], ['auth']],
    ['GET',  '/rtc/history',                      [RtcController::class, 'studentHistory'],         ['auth']],
    ['GET',  '/rtc/create',                       [RtcController::class, 'createForm'],             ['auth']],
    ['POST', '/rtc/create',                       [RtcController::class, 'store'],                  ['auth']],
    ['GET',  '/rtc/{id}',                         [RtcController::class, 'detail'],                 ['auth']],
    ['POST', '/rtc/{id}/approve',                 [RtcController::class, 'approve'],                ['auth']],
    ['POST', '/rtc/{id}/reject',                  [RtcController::class, 'reject'],                 ['auth']],

    // --- Module 7: Notifications ---
    ['GET',  '/notifications',                    [NotificationController::class, 'index'],          ['auth']],
    ['POST', '/notifications/send',               [NotificationController::class, 'send'],           ['auth']],
    ['GET',  '/notifications/errors',             [NotificationController::class, 'errors'],         ['auth']],

    // --- Module 9: Staff Management (static paths first) ---
    ['GET',  '/staff/create',                     [StaffController::class,     'createForm'],        ['auth']],
    ['POST', '/staff/create',                     [StaffController::class,     'store'],             ['auth']],
    ['GET',  '/staff/profile',                    [StaffSelfController::class, 'profileForm'],       ['auth']],
    ['POST', '/staff/profile',                    [StaffSelfController::class, 'profileUpdate'],     ['auth']],
    ['GET',  '/staff/change-password',            [StaffSelfController::class, 'changePasswordForm'],['auth']],
    ['POST', '/staff/change-password',            [StaffSelfController::class, 'changePassword'],    ['auth']],
    ['GET',  '/staff',                            [StaffController::class,     'index'],             ['auth']],
    ['GET',  '/staff/{id}/edit',                  [StaffController::class,     'editForm'],          ['auth']],
    ['POST', '/staff/{id}/edit',                  [StaffController::class,     'update'],            ['auth']],
    ['POST', '/staff/{id}/toggle-status',         [StaffController::class,     'toggleStatus'],      ['auth']],
    ['GET',  '/staff/{id}/reset-password',        [StaffController::class,     'resetPasswordForm'], ['auth']],
    ['POST', '/staff/{id}/reset-password',        [StaffController::class,     'resetPassword'],     ['auth']],

    // --- Module 10: Field Management (static paths BEFORE {param} wildcards) ---
    ['GET',  '/field-config/my-dept',             [FieldConfigController::class, 'myDept'],           ['auth']],
    ['GET',  '/field-config/custom',              [CustomFieldController::class, 'index'],            ['auth']],
    ['GET',  '/field-config/custom/create',       [CustomFieldController::class, 'createForm'],      ['auth']],
    ['POST', '/field-config/custom/create',       [CustomFieldController::class, 'store'],           ['auth']],
    ['GET',  '/field-config/custom/{id}/edit',    [CustomFieldController::class, 'editForm'],        ['auth']],
    ['POST', '/field-config/custom/{id}/edit',    [CustomFieldController::class, 'update'],          ['auth']],
    ['POST', '/field-config/custom/{id}/toggle',  [CustomFieldController::class, 'toggleStatus'],    ['auth']],
    ['GET',  '/field-config',                     [FieldConfigController::class, 'index'],           ['auth']],
    ['POST', '/field-config',                     [FieldConfigController::class, 'saveBulk'],        ['auth']],
    ['GET',  '/field-config/{deptId}',            [FieldConfigController::class, 'deptView'],        ['auth']],
    ['POST', '/field-config/{deptId}',            [FieldConfigController::class, 'saveDeptBulk'],    ['auth']],
    ['POST', '/field-config/{deptId}/reset',      [FieldConfigController::class, 'resetDept'],       ['auth']],
];

foreach ($routes as [$m, $path, $handler, $mw]) {
    $params  = [];
    $matched = ($m === $method) && (
        $path === $uri || matchRoute($path, $uri, $params)
    );
    if (!$matched) continue;

    foreach ($mw as $name) {
        if ($name === 'auth') AuthMiddleware::handle();
    }

    if ($handler instanceof \Closure) {
        $handler();
        exit;
    }

    [$class, $action] = $handler;
    $controller = new $class();
    if (!empty($params)) {
        $castParams = array_map(fn($v) => ctype_digit((string)$v) ? (int)$v : $v, array_values($params));
        $controller->{$action}(...$castParams);
    } else {
        $controller->{$action}();
    }
    exit;
}

// No route matched
View::render('errors/404', ['title' => 'Not found'], 404);
