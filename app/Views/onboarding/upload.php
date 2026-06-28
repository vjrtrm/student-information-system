<?php
use App\Helpers\Csrf;
use App\Helpers\View;
$title = $data['title'] ?? 'Upload Students';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Upload Students</h1>
    <div>
        <a href="/onboarding/template" class="btn btn-outline-secondary btn-sm me-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download me-1" viewBox="0 0 16 16">
                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
            </svg>
            Download Template
        </a>
        <a href="/onboarding" class="btn btn-outline-secondary btn-sm">Back to Students</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Before uploading:</strong>
            <ul class="mb-0 mt-1">
                <li>Use the <strong>Download Template</strong> button to get the correct .xlsx format.</li>
                <li>Only <strong>.xlsx</strong> files are accepted (not .xls or .csv).</li>
                <li>Maximum file size: <strong>5 MB</strong>.</li>
                <li>Maximum rows per upload: <strong>1,000 students</strong>. Split larger files.</li>
                <li>Refer to the <em>Valid Values</em> sheet in the template for allowed Gender, Academic Year, Class and Section values.</li>
                <li>Dates must be in <strong>DD/MM/YYYY</strong> format.</li>
                <li>Mobile numbers must be exactly <strong>10 digits</strong>.</li>
            </ul>
        </div>

        <form method="POST" action="/onboarding/upload" enctype="multipart/form-data" id="uploadForm">
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label for="students_file" class="form-label fw-semibold">Select .xlsx File</label>
                <input
                    type="file"
                    class="form-control"
                    id="students_file"
                    name="students_file"
                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    required
                >
                <div class="form-text" id="fileSizeNote"></div>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                <span class="spinner-border spinner-border-sm d-none me-1" id="uploadSpinner" role="status" aria-hidden="true"></span>
                Upload Students
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('students_file').addEventListener('change', function () {
    const maxBytes = 5 * 1024 * 1024;
    const note = document.getElementById('fileSizeNote');
    if (this.files.length && this.files[0].size > maxBytes) {
        note.textContent = 'This file exceeds 5 MB and will be rejected. Please split it.';
        note.className = 'form-text text-danger';
        document.getElementById('submitBtn').disabled = true;
    } else {
        note.textContent = '';
        document.getElementById('submitBtn').disabled = false;
    }
});

document.getElementById('uploadForm').addEventListener('submit', function () {
    document.getElementById('uploadSpinner').classList.remove('d-none');
    document.getElementById('submitBtn').disabled = true;
});
</script>
