<?php

require_once __DIR__.'/core/helper.php';

$pageTitle = 'Unauthorized Access';
flash('error', 'You do not have permission to access this page.');

?>


    <?php require_once  __DIR__ . '/partials/head.php'; ?>
    <?php require_once  __DIR__ . '/partials/preload.php'; ?>
    <body class="error-page">
        <div id="layoutError">
            <div id="layoutError_content">
                <main>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-6">
                                <div class="text-center mt-4">
                                    <h1 class="display-1">401</h1>
                                    <p class="lead">Unauthorized </p>
                                    <p>Access to this resource is denied.</p>
                                    <a href="logout.php"><i class="fas fa-arrow-left mr-1"></i>Return to Dashboard</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

    <?php require_once  __DIR__ . '/partials/scripts.php'; ?>
