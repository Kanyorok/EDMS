<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Replaces hardcoded English strings in blade views with __() translation calls.
 *
 * Strategy:
 *   1. Preserve <script>, <style>, @php blocks and existing {{ }} expressions.
 *   2. In the remaining HTML, apply a dictionary of exact-string replacements.
 *   3. Restore preserved regions and write the file.
 *
 * Safety rules:
 *   - Only replaces text that appears between HTML tags (> ... </) — never
 *     inside attribute values such as value="", class="", id="".
 *   - placeholder="" and title="" attributes ARE translated.
 *   - Replacements are sorted longest-first so "Apply Filters" is caught
 *     before "Filter" or "Apply".
 *   - Idempotent: running twice does not double-wrap a key.
 */
class TranslateViews extends Command
{
    protected $signature = 'views:translate
                            {--module=     : Only process a specific top-level module directory (e.g. hr)}
                            {--dry-run     : Report changes without writing files}
                            {--stats       : Show per-file replacement counts}';

    protected $description = 'Replace hardcoded English UI strings in blade views with __() calls';

    // ─── Dictionary ────────────────────────────────────────────────────────
    // Format: 'Exact English string' => ['translation_file', 'key']
    // Sorted longest-first at runtime to avoid partial replacements.
    private array $dictionary = [

        // ── Common: multi-word strings (must come before single-word) ──────
        'Apply Filters'             => ['common', 'apply_filters'],
        'Reset Filters'             => ['common', 'reset_filters'],
        'Save Changes'              => ['common', 'save_changes'],
        'Back to List'              => ['common', 'back_to_list'],
        'Add New'                   => ['common', 'add_new'],
        'New Record'                => ['common', 'new_record'],
        'Select All'                => ['common', 'select_all'],
        'Deselect All'              => ['common', 'deselect_all'],
        'Download Template'         => ['common', 'download_template'],
        'Send Emails'               => ['common', 'send_emails'],
        'Send Email'                => ['common', 'send_email'],
        'Sign In'                   => ['common', 'sign_in'],
        'Log Out'                   => ['common', 'log_out'],
        'No records found.'         => ['common', 'no_records_found'],
        'No records found'          => ['common', 'no_records_found'],
        'No data available.'        => ['common', 'no_data'],
        'No data available'         => ['common', 'no_data'],
        'No attachments uploaded.'  => ['common', 'no_attachments'],
        'No attachments uploaded'   => ['common', 'no_attachments'],
        'No image attached'         => ['common', 'no_image'],
        'Loading...'                => ['common', 'loading'],
        'Audit Information'         => ['common', 'audit_info'],
        'Audit Trail'               => ['common', 'audit_trail'],
        'Workflow History'          => ['common', 'workflow_history'],
        'Please fix the following:' => ['common', 'please_fix'],
        'Fix the following:'        => ['common', 'fix_following'],
        'All Branches'              => ['common', 'all_branches'],
        'All Departments'           => ['common', 'all_departments'],
        'All Statuses'              => ['common', 'all_statuses'],
        'All Categories'            => ['common', 'all_categories'],
        'All Types'                 => ['common', 'all_types'],
        'All Channels'              => ['common', 'all_channels'],
        'Per Page'                  => ['common', 'per_page'],
        'Date From'                 => ['common', 'date_from'],
        'Date To'                   => ['common', 'date_to'],
        'Select Branch'             => ['common', 'select_branch'],
        'Select Department'         => ['common', 'select_department'],
        'Select Country'            => ['common', 'select_country'],
        'Select Country first'      => ['common', 'select_country_first'],
        'Select City'               => ['common', 'select_city'],
        'Error loading cities'      => ['common', 'error_loading_cities'],
        'Is Required'               => ['common', 'is_required'],
        'Is Active'                 => ['common', 'is_active'],
        'Sort Order'                => ['common', 'sort_order'],
        'Max File Size (KB)'        => ['common', 'max_file_size_kb'],
        'Allowed Extensions'        => ['common', 'allowed_extensions'],
        'Visible On Approval'       => ['common', 'visible_on_approval'],
        'Tax / VAT (if any)'        => ['common', 'tax_vat'],
        'Total Payable'             => ['common', 'total_payable'],
        'Date Uploaded'             => ['common', 'date_uploaded'],
        'ID Number'                 => ['common', 'id_number'],
        'Full Name'                 => ['common', 'full_name'],
        'Unit Cost'                 => ['common', 'unit_cost'],
        'Unit Price'                => ['common', 'unit_price'],
        'Line Total'                => ['common', 'line_total'],
        'GL Account'                => ['common', 'gl_account'],
        'Sub Total'                 => ['common', 'sub_total'],
        'Created At'                => ['common', 'created_at'],
        'Updated At'                => ['common', 'updated_at'],
        'Created By'                => ['common', 'created_by'],
        'Updated By'                => ['common', 'updated_by'],
        'Modified By'               => ['common', 'modified_by'],
        'Approved By'               => ['common', 'approved_by'],
        'Due Date'                  => ['common', 'due_date'],
        'Start Date'                => ['common', 'start_date'],
        'End Date'                  => ['common', 'end_date'],
        'Branch Code'               => ['common', 'branch_code'],
        'Branch Name'               => ['common', 'branch_name'],
        'Bank Name'                 => ['common', 'bank_name'],
        'Bank Branch'               => ['common', 'bank_branch'],
        'Bank Account'              => ['common', 'bank_account'],
        'Account Number'            => ['common', 'account_number'],
        'Account Holder Name'       => ['common', 'account_holder'],
        'Account Type'              => ['common', 'account_type'],
        'Account Status'            => ['common', 'status'],
        'Zip Code'                  => ['common', 'zip_code'],
        'Document Type'             => ['common', 'document_type'],
        'Document Title'            => ['common', 'document_title'],
        'On Hold'                   => ['common', 'status_on_hold'],
        'Address 1'                 => ['common', 'address1'],
        'Address 2'                 => ['common', 'address2'],

        // ── Common: single-word buttons / actions ──────────────────────────
        'Save'                      => ['common', 'save'],
        'Cancel'                    => ['common', 'cancel'],
        'Edit'                      => ['common', 'edit'],
        'Delete'                    => ['common', 'delete'],
        'View'                      => ['common', 'view'],
        'Create'                    => ['common', 'create'],
        'Add'                       => ['common', 'add'],
        'Remove'                    => ['common', 'remove'],
        'Update'                    => ['common', 'update'],
        'Submit'                    => ['common', 'submit'],
        'Approve'                   => ['common', 'approve'],
        'Reject'                    => ['common', 'reject'],
        'Back'                      => ['common', 'back'],
        'Export'                    => ['common', 'export'],
        'Print'                     => ['common', 'print'],
        'Download'                  => ['common', 'download'],
        'Upload'                    => ['common', 'upload'],
        'Search'                    => ['common', 'search'],
        'Filter'                    => ['common', 'filter'],
        'Reset'                     => ['common', 'reset'],
        'Close'                     => ['common', 'close'],
        'Confirm'                   => ['common', 'confirm'],
        'Yes'                       => ['common', 'yes'],
        'Resolve'                   => ['common', 'resolve'],
        'Send'                      => ['common', 'send'],
        'Preview'                   => ['common', 'preview'],
        'Proceed'                   => ['common', 'proceed'],
        'Retry'                     => ['common', 'retry'],
        'Refresh'                   => ['common', 'refresh'],
        'Clear'                     => ['common', 'clear'],
        'Select'                    => ['common', 'select'],
        'Continue'                  => ['common', 'continue'],
        'Previous'                  => ['common', 'previous'],
        'Next'                      => ['common', 'next'],
        'Finish'                    => ['common', 'finish'],
        'Generate'                  => ['common', 'generate'],
        'Process'                   => ['common', 'process'],
        'Apply'                     => ['common', 'apply'],
        'Assign'                    => ['common', 'assign'],
        'Unassign'                  => ['common', 'unassign'],
        'Transfer'                  => ['common', 'transfer'],
        'Post'                      => ['common', 'post'],
        'Void'                      => ['common', 'void'],
        'Reverse'                   => ['common', 'reverse'],
        'Activate'                  => ['common', 'activate'],
        'Deactivate'                => ['common', 'deactivate'],
        'Archive'                   => ['common', 'archive'],
        'Restore'                   => ['common', 'restore'],
        'Duplicate'                 => ['common', 'duplicate'],
        'Copy'                      => ['common', 'copy'],
        'Import'                    => ['common', 'import'],
        'Publish'                   => ['common', 'publish'],
        'Open'                      => ['common', 'open'],

        // ── Common: table column headers ───────────────────────────────────
        'Actions'                   => ['common', 'actions'],
        'Status'                    => ['common', 'status'],
        'Name'                      => ['common', 'name'],
        'Description'               => ['common', 'description'],
        'Date'                      => ['common', 'date'],
        'Amount'                    => ['common', 'amount'],
        'Type'                      => ['common', 'type'],
        'Code'                      => ['common', 'code'],
        'Reference'                 => ['common', 'reference'],
        'Notes'                     => ['common', 'notes'],
        'Total'                     => ['common', 'total'],
        'Branch'                    => ['common', 'branch'],
        'Department'                => ['common', 'department'],
        'Employee'                  => ['common', 'employee'],
        'Category'                  => ['common', 'category'],
        'Supplier'                  => ['common', 'supplier'],
        'Party'                     => ['common', 'party'],
        'Value'                     => ['common', 'value'],
        'Balance'                   => ['common', 'balance'],
        'Qty'                       => ['common', 'qty'],
        'Quantity'                  => ['common', 'quantity'],
        'Tax'                       => ['common', 'tax'],
        'Currency'                  => ['common', 'currency'],
        'Account'                   => ['common', 'account'],
        'Subtotal'                  => ['common', 'subtotal'],
        'Discount'                  => ['common', 'discount'],
        'Price'                     => ['common', 'price'],
        'Rate'                      => ['common', 'rate'],
        'Title'                     => ['common', 'title'],
        'Subject'                   => ['common', 'subject'],
        'Period'                    => ['common', 'period'],
        'Year'                      => ['common', 'year'],
        'Month'                     => ['common', 'month'],
        'Week'                      => ['common', 'week'],
        'Day'                       => ['common', 'day'],
        'Time'                      => ['common', 'time'],
        'Duration'                  => ['common', 'duration'],
        'Location'                  => ['common', 'location'],
        'Priority'                  => ['common', 'priority'],
        'Assignee'                  => ['common', 'assignee'],
        'Owner'                     => ['common', 'owner'],
        'User'                      => ['common', 'user'],
        'Role'                      => ['common', 'role'],
        'Grade'                     => ['common', 'grade'],
        'Order'                     => ['common', 'order'],
        'Stage'                     => ['common', 'stage'],
        'Source'                    => ['common', 'source'],
        'Target'                    => ['common', 'target'],
        'Actual'                    => ['common', 'actual'],
        'Variance'                  => ['common', 'variance'],
        'Remarks'                   => ['common', 'remarks'],
        'Dated'                     => ['common', 'dated'],
        'Number'                    => ['common', 'number'],
        'File'                      => ['common', 'file'],
        'Channel'                   => ['common', 'channel'],
        'Team'                      => ['common', 'team'],
        'Manager'                   => ['common', 'manager'],
        'Supervisor'                => ['common', 'supervisor'],
        'UOM'                       => ['common', 'uom'],
        'Town'                      => ['common', 'town'],
        'Country'                   => ['common', 'country'],
        'City'                      => ['common', 'city'],
        'Email'                     => ['common', 'email'],
        'Phone'                     => ['common', 'phone'],
        'Address'                   => ['common', 'address'],
        'Contact'                   => ['common', 'contact'],
        'Attachments'               => ['common', 'attachments'],
        'Attachment'                => ['common', 'attachment'],
        'Size'                      => ['common', 'size'],
        'Photo'                     => ['common', 'photo'],
        'Label'                     => ['common', 'label'],
        'Content'                   => ['common', 'content'],
        'Operation'                 => ['common', 'operation'],
        'Module'                    => ['common', 'module'],
        'Bank'                      => ['common', 'bank'],
        'Banks'                     => ['common', 'banks'],
        'Savings'                   => ['common', 'savings'],
        'Checking'                  => ['common', 'checking'],
        'Others'                    => ['common', 'others'],

        // ── Common: filter labels ──────────────────────────────────────────
        'All'                       => ['common', 'all'],
        'Show'                      => ['common', 'show'],
        'Results'                   => ['common', 'results'],
        'From'                      => ['common', 'from'],
        'To'                        => ['common', 'to'],

        // ── Common: status values ──────────────────────────────────────────
        'Active'                    => ['common', 'status_active'],
        'Inactive'                  => ['common', 'status_inactive'],
        'Pending'                   => ['common', 'status_pending'],
        'Draft'                     => ['common', 'status_draft'],
        'Approved'                  => ['common', 'status_approved'],
        'Rejected'                  => ['common', 'status_rejected'],
        'Completed'                 => ['common', 'status_completed'],
        'Cancelled'                 => ['common', 'status_cancelled'],
        'Processing'                => ['common', 'status_processing'],
        'Closed'                    => ['common', 'status_closed'],
        'Deactivated'               => ['common', 'status_deactivated'],
        'Suspended'                 => ['common', 'status_suspended'],
        'Expired'                   => ['common', 'status_expired'],
        'Overdue'                   => ['common', 'status_overdue'],
        'Paid'                      => ['common', 'status_paid'],
        'Unpaid'                    => ['common', 'status_unpaid'],
        'Partial'                   => ['common', 'status_partial'],
        'Posted'                    => ['common', 'status_posted'],
        'Voided'                    => ['common', 'status_voided'],
        'Submitted'                 => ['common', 'status_submitted'],
        'Processed'                 => ['common', 'status_processed'],
        'Unassigned'                => ['common', 'status_unassigned'],

        // ── Common: UI sections ────────────────────────────────────────────
        'Home'                      => ['common', 'home'],
        'Settings'                  => ['common', 'settings'],
        'Information'               => ['common', 'information'],
        'Details'                   => ['common', 'details'],
        'Overview'                  => ['common', 'overview'],
        'Summary'                   => ['common', 'summary'],
        'Reports'                   => ['common', 'reports'],
        'Report'                    => ['common', 'report'],
        'Dashboard'                 => ['common', 'dashboard'],
        'Profile'                   => ['common', 'profile'],
        'History'                   => ['common', 'history'],
        'Logs'                      => ['common', 'logs'],
        'Log'                       => ['common', 'log'],
        'Comments'                  => ['common', 'comments'],
        'Messages'                  => ['common', 'messages'],
        'Activities'                => ['common', 'activities'],
        'Tasks'                     => ['common', 'tasks'],
        'Schedule'                  => ['common', 'schedule'],
        'Calendar'                  => ['common', 'calendar'],

        // ── Finance module ─────────────────────────────────────────────────
        'Invoice Information'       => ['finance', 'invoice_information'],
        'Supplier Information'      => ['finance', 'supplier_information'],
        'Reference Information'     => ['finance', 'reference_information'],
        'Invoice Lines'             => ['finance', 'invoice_lines'],
        'Transaction Types'         => ['finance', 'transaction_types'],
        'Transaction Name'          => ['finance', 'transaction_name'],
        'Transaction Code'          => ['finance', 'transaction_code'],
        'Update Transaction Type'   => ['finance', 'update_transaction'],
        'Payment Terms'             => ['finance', 'payment_terms'],

        // ── Procurement module ─────────────────────────────────────────────
        'Reason for Rejection'      => ['procurement', 'reason_for_rejection'],
        'Publish to Suppliers'      => ['procurement', 'publish_to_suppliers'],
        'Supplier Quotations'       => ['procurement', 'supplier_quotations'],
        'Submission Deadline'       => ['procurement', 'submission_deadline'],
        'Requisition Items Details:' => ['procurement', 'requisition_items'],
        'Create Requirement'        => ['procurement', 'create_requirement'],

        // ── HR module ─────────────────────────────────────────────────────
        'Employee Onboarding Wizard' => ['hr', 'employee_onboarding_wizard'],
        'Emergency Contacts / Next of Kin' => ['hr', 'emergency_contacts'],
        'Employment Date'           => ['hr', 'employment_date'],
        'Employment Type'           => ['hr', 'employment_type'],
        'Contract Type'             => ['hr', 'contract_type'],
        'Tax Exempt'                => ['hr', 'tax_exempt'],
        'Basic Salary'              => ['hr', 'basic_salary'],
        'Payment Mode'              => ['hr', 'payment_mode'],
        'National ID No'            => ['hr', 'national_id_no'],
        'Identity Type'             => ['hr', 'identity_type'],
        'Attendance Logs'           => ['hr', 'attendance_logs'],
        'Daily Attendance'          => ['hr', 'daily_attendance'],
        'Attendance Exceptions'     => ['hr', 'attendance_exceptions_title'],
        'Attendance Summary'        => ['hr', 'attendance_summary'],
        'KPI Goals'                 => ['hr', 'kpi_goals'],
        'Total Weight'              => ['hr', 'total_weight'],
        'Date From'                 => ['common', 'date_from'],
        'Date To'                   => ['common', 'date_to'],
        'Employee No'               => ['hr', 'employee_no'],

        // ── Property module ────────────────────────────────────────────────
        'Tenant Ledger'             => ['property', 'tenant_ledger'],
        'Select Tenant'             => ['property', 'select_tenant'],
        'Property Attachment Details' => ['property', 'property_attachment'],
        'Description / Notes'       => ['property', 'document_notes'],
        'Debit (KES)'               => ['property', 'debit_kes'],
        'Credit (KES)'              => ['property', 'credit_kes'],

        // ── Fleet module ───────────────────────────────────────────────────
        'Assigned By'               => ['fleet', 'assigned_by'],
        'Assigned On'               => ['fleet', 'assigned_on'],
        'Unassigned On'             => ['fleet', 'unassigned_on'],
        'Repair Type'               => ['fleet', 'repair_type'],
        'Repair Date'               => ['fleet', 'repair_date'],
        'Vendor Name'               => ['fleet', 'vendor_name'],
        'Select Vendor'             => ['fleet', 'select_vendor'],
        'Supporting Documents'      => ['fleet', 'supporting_docs'],
        'Driver Name'               => ['fleet', 'driver_name'],
        'Driver Setup'              => ['fleet', 'driver_setup'],

        // ── Legal module ───────────────────────────────────────────────────
        'Regulatory Bodies'         => ['legal', 'regulatory_bodies'],
        'Contact Person'            => ['legal', 'contact_person'],
        'Contact Email'             => ['legal', 'contact_email'],
        'Contact Phone'             => ['legal', 'contact_phone'],
        'Draft Title'               => ['legal', 'draft_title'],
        'Created On'                => ['legal', 'created_on'],

        // ── Inventory module ───────────────────────────────────────────────
        'Stores List'               => ['inventory', 'stores_list'],
        'Store Name'                => ['inventory', 'store_name'],
        'Store ID'                  => ['inventory', 'store_id'],
        'Inventory Review Details'  => ['inventory', 'inventory_review'],
    ];

    // Directories to skip entirely
    private array $skipPaths = [
        'layouts', 'emails', 'exports', 'vendor', 'components',
        'partials', 'snippets', 'errors',
    ];

    // ─── Main ──────────────────────────────────────────────────────────────
    public function handle(): int
    {
        $viewsPath = resource_path('views');
        $module    = $this->option('module');
        $dryRun    = $this->option('dry-run');
        $showStats = $this->option('stats');

        $finder = new Finder();
        $finder->files()->name('*.blade.php')->in($viewsPath);

        foreach ($this->skipPaths as $skip) {
            $finder->notPath($skip);
        }

        if ($module) {
            $finder->path($module . '/');
        }

        // Sort dictionary longest-first
        $sorted = $this->dictionary;
        uksort($sorted, fn($a, $b) => strlen($b) - strlen($a));

        $totalFiles    = 0;
        $modifiedFiles = 0;
        $totalReplacements = 0;

        foreach ($finder as $file) {
            $original = $file->getContents();
            $modified = $this->processContent($original, $sorted);

            $totalFiles++;

            if ($modified === $original) {
                continue;
            }

            $count = substr_count($modified, "__(") - substr_count($original, "__(");
            $totalReplacements += max(0, $count);
            $modifiedFiles++;

            if ($showStats || $dryRun) {
                $this->line(sprintf(
                    '  %s %s (+%d replacements)',
                    $dryRun ? '[DRY]' : '[MOD]',
                    $file->getRelativePathname(),
                    max(0, $count)
                ));
            }

            if (!$dryRun) {
                file_put_contents($file->getRealPath(), $modified);
            }
        }

        $verb = $dryRun ? 'Would modify' : 'Modified';
        $this->info("Scanned {$totalFiles} files. {$verb} {$modifiedFiles} files ({$totalReplacements} replacements).");

        return 0;
    }

    // ─── Core processing ───────────────────────────────────────────────────
    private function processContent(string $content, array $sorted): string
    {
        // Step 1: Extract and preserve non-translatable regions.
        $preserved = [];
        $index     = 0;

        $content = preg_replace_callback(
            '/(<script\b[^>]*>.*?<\/script>)/is',
            function ($m) use (&$preserved, &$index) {
                $k = "___PRESERVE_{$index}___";
                $index++;
                $preserved[$k] = $m[0];
                return $k;
            },
            $content
        );

        $content = preg_replace_callback(
            '/(<style\b[^>]*>.*?<\/style>)/is',
            function ($m) use (&$preserved, &$index) {
                $k = "___PRESERVE_{$index}___";
                $index++;
                $preserved[$k] = $m[0];
                return $k;
            },
            $content
        );

        $content = preg_replace_callback(
            '/(@php\b.*?@endphp)/is',
            function ($m) use (&$preserved, &$index) {
                $k = "___PRESERVE_{$index}___";
                $index++;
                $preserved[$k] = $m[0];
                return $k;
            },
            $content
        );

        // Preserve existing {{ }} and {!! !!} expressions
        $content = preg_replace_callback(
            '/(\{\{.*?\}\})/s',
            function ($m) use (&$preserved, &$index) {
                $k = "___PRESERVE_{$index}___";
                $index++;
                $preserved[$k] = $m[0];
                return $k;
            },
            $content
        );

        $content = preg_replace_callback(
            '/(\{!!.*?!!\})/s',
            function ($m) use (&$preserved, &$index) {
                $k = "___PRESERVE_{$index}___";
                $index++;
                $preserved[$k] = $m[0];
                return $k;
            },
            $content
        );

        // Preserve Blade comments
        $content = preg_replace_callback(
            '/(\{\{--.*?--\}\})/s',
            function ($m) use (&$preserved, &$index) {
                $k = "___PRESERVE_{$index}___";
                $index++;
                $preserved[$k] = $m[0];
                return $k;
            },
            $content
        );

        // Step 2: Apply dictionary replacements.
        foreach ($sorted as $search => $meta) {
            [$file, $key] = $meta;
            $replacement  = "{{ __('{$file}.{$key}') }}";
            $escaped      = preg_quote($search, '/');

            // 2a. Text content between HTML tags: >  STRING  </
            $content = preg_replace(
                '/(>)\s*' . $escaped . '\s*(<\/)/u',
                '$1' . $replacement . '$2',
                $content
            );

            // 2b. Standalone trimmed line (text node alone on a line)
            $content = preg_replace(
                '/^(\s*)' . $escaped . '(\s*)$/mu',
                '$1' . $replacement . '$2',
                $content
            );

            // 2c. placeholder="STRING"
            $content = str_replace(
                'placeholder="' . $search . '"',
                'placeholder="' . $replacement . '"',
                $content
            );
            $content = str_replace(
                "placeholder='" . $search . "'",
                "placeholder='" . $replacement . "'",
                $content
            );

            // 2d. title="STRING"
            $content = str_replace(
                'title="' . $search . '"',
                'title="' . $replacement . '"',
                $content
            );
        }

        // Step 3: Restore preserved regions.
        return str_replace(array_keys($preserved), array_values($preserved), $content);
    }
}
