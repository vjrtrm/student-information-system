<?php
namespace App\Controllers;

use App\Helpers\MasterAuditLogger;
use App\Middleware\RoleMiddleware;
use App\Models\OptionList;
use App\Models\OptionValue;

class OptionListController extends Controller
{
    public function index(): void
    {
        RoleMiddleware::handle(['institution_admin']);

        $lists = OptionList::withCounts();

        $this->render('master-data/option-lists/index', [
            'lists' => $lists,
            'title' => 'Option Lists',
        ]);
    }

    public function show(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);

        $list = OptionList::find($id);
        if (!$list) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        $values = OptionValue::byList($id, false);

        $this->render('master-data/option-lists/show', [
            'list'   => $list,
            'values' => $values,
            'title'  => $list['label'] . ' Values',
        ]);
    }

    public function storeValue(int $id): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $list = OptionList::find($id);
        if (!$list) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        $value   = (string)$this->input('value', '');
        $display = (string)$this->input('display', '');

        if ($error = $this->validateValueFields($value, $display)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $error];
            $this->redirect('/master-data/option-lists/' . $id);
            return;
        }

        $sortOrder = OptionValue::maxSortOrder($id) + 10;
        $vid       = OptionValue::create($id, $value, $display, $sortOrder);

        MasterAuditLogger::log('create', 'option_value', $vid, [
            'list_id' => $id,
            'value'   => $value,
            'display' => $display,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Option value added successfully.'];
        $this->redirect('/master-data/option-lists/' . $id);
    }

    public function updateValue(int $id, int $vid): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        $optionValue = OptionValue::find($vid);
        if (!$optionValue) {
            $this->render('errors/404', ['title' => 'Not Found'], 404);
            exit;
        }

        $value     = (string)$this->input('value', '');
        $display   = (string)$this->input('display', '');
        $sortOrder = (int)$this->input('sort_order', 0);

        if ($error = $this->validateValueFields($value, $display)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $error];
            $this->redirect('/master-data/option-lists/' . $id);
            return;
        }

        OptionValue::update($vid, $value, $display, $sortOrder);

        MasterAuditLogger::log('update', 'option_value', $vid, [
            'list_id'    => $id,
            'value'      => $value,
            'display'    => $display,
            'sort_order' => $sortOrder,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Option value updated successfully.'];
        $this->redirect('/master-data/option-lists/' . $id);
    }

    public function deactivateValue(int $id, int $vid): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        OptionValue::deactivate($vid);
        MasterAuditLogger::log('deactivate', 'option_value', $vid, ['list_id' => $id]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Option value deactivated.'];
        $this->redirect('/master-data/option-lists/' . $id);
    }

    public function reactivateValue(int $id, int $vid): void
    {
        RoleMiddleware::handle(['institution_admin']);
        $this->requireCsrf();

        OptionValue::reactivate($vid);
        MasterAuditLogger::log('reactivate', 'option_value', $vid, ['list_id' => $id]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Option value reactivated.'];
        $this->redirect('/master-data/option-lists/' . $id);
    }

    // ---- helpers ----

    /** Returns an error string or null if valid. */
    private function validateValueFields(string $value, string $display): ?string
    {
        if ($value === '' || strlen($value) > 150) {
            return 'Value is required and must not exceed 150 characters.';
        }
        if ($display === '' || strlen($display) > 150) {
            return 'Display label is required and must not exceed 150 characters.';
        }
        return null;
    }
}
