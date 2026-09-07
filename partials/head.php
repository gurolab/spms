<?php
require_once __DIR__ . '/../core/csrf.php';
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <!-- Title -->
    <title><?=$pageTitle;?> | SMS</title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?=IMG_PATH?>/logo/cotsu.png">
    <!-- Bootstrap -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/bootstrap.min.css">
    <!-- file upload -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/file-upload.css">
    <!-- file upload -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/plyr.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <!-- full calendar -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/full-calendar.css">
    <!-- jquery Ui -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/jquery-ui.css">
    <!-- editor quill Ui -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/editor-quill.css">
    <!-- apex charts Css -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/apexcharts.css">
    <!-- calendar Css -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/calendar.css">
    <!-- jvector map Css -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/jquery-jvectormap-2.0.5.css">
    <!-- Main css -->
    <link rel="stylesheet" href="<?=CSS_PATH?>/main.css">

    <style>
    /* Custom positioning for alerts */
    .alert-container {
        position: fixed;
        top: 20px;
        right: 20px;
        max-width: 350px;
        z-index: 9999;
    }
    
    /* Add animation */
    .alert-fade {
        opacity: 0;
        animation: fadeIn 0.5s ease-in forwards;
    }
    
    @keyframes fadeIn {
        from {opacity: 0; transform: translateY(-20px);}
        to {opacity: 1; transform: translateY(0);}
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        border: 1px solid #d4d8e0;
        background-color: #ffffff;
        color: #475569;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .btn-action:hover,
    .btn-action:focus {
        background-color: #eef2ff;
        border-color: #6366f1;
        color: #4338ca;
    }

    .btn-action.btn-action-primary {
        border-color: #6366f1;
        color: #4f46e5;
    }

    .btn-action.btn-action-primary:hover,
    .btn-action.btn-action-primary:focus {
        background-color: #eef2ff;
        color: #312e81;
    }

    .btn-action.btn-action-danger {
        border-color: #dc2626;
        color: #dc2626;
    }

    .btn-action.btn-action-danger:hover,
    .btn-action.btn-action-danger:focus {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .btn-action + .btn-action {
        margin-left: 6px;
    }

    /* Restore readable Bootstrap button variants overridden by template .btn color */
    .btn.btn-outline-primary {
        color: #2563eb !important;
        border-color: #2563eb !important;
        background-color: transparent !important;
    }

    .btn.btn-outline-primary:hover,
    .btn.btn-outline-primary:focus {
        color: #ffffff !important;
        background-color: #2563eb !important;
    }

    .btn.btn-outline-secondary {
        color: #334155 !important;
        border-color: #94a3b8 !important;
        background-color: transparent !important;
    }

    .btn.btn-outline-secondary:hover,
    .btn.btn-outline-secondary:focus {
        color: #ffffff !important;
        background-color: #475569 !important;
        border-color: #475569 !important;
    }

    .btn.btn-outline-danger {
        color: #dc2626 !important;
        border-color: #dc2626 !important;
        background-color: transparent !important;
    }

    .btn.btn-outline-danger:hover,
    .btn.btn-outline-danger:focus {
        color: #ffffff !important;
        background-color: #dc2626 !important;
        border-color: #dc2626 !important;
    }

    .btn.btn-light {
        color: #0f172a !important;
        border-color: #cbd5e1 !important;
        background-color: #f8fafc !important;
    }

    .btn.btn-link {
        color: #2563eb !important;
        border-color: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
    }

    .btn.btn-link.text-danger {
        color: #dc2626 !important;
    }

    /* Normalize Select2 dimensions against Bootstrap form controls */
    .select2-container {
        width: 100% !important;
    }

    .select2-container .select2-selection--single {
        min-height: 42px;
        display: flex;
        align-items: center;
        border: 1px solid #d0d5dd;
        border-radius: 0.375rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        padding-left: 12px;
        padding-right: 30px;
        color: #111827;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100%;
        right: 8px;
    }

    .select2-container .select2-selection--multiple {
        min-height: 42px;
        border: 1px solid #d0d5dd;
        border-radius: 0.375rem;
    }

    /* Keep dense admin tables and sidebar labels readable across themes */
    .table thead th.text-gray-300,
    .table thead th.text-gray-200,
    .table thead th {
        color: #334155 !important;
    }

    .table tbody td,
    .table tbody th {
        color: #0f172a;
    }

    .sidebar-menu__link .text,
    .sidebar-submenu__link {
        color: #334155 !important;
    }

    .sidebar-menu__link .icon {
        color: #475569;
    }

    /* Improve breadcrumb/table/footer readability in module pages */
    .breadcrumb a.text-gray-200,
    .breadcrumb .text-gray-200,
    .breadcrumb .text-gray-300 {
        color: #475569 !important;
    }

    .dashboard-footer p.text-gray-300 {
        color: #475569 !important;
    }

    .modal-content .modal-title,
    .modal-content .form-label,
    .modal-content .form-text,
    .modal-content p,
    .modal-content small {
        color: #0f172a;
    }
    </style>
</head> 
