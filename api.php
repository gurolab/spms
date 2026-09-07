<?php

require_once __DIR__ . '/core/flash.php';
require_once __DIR__ . '/models/User.model.php';
require_once __DIR__ . '/models/Docs.model.php';
require_once __DIR__ . '/models/Program.model.php';


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST');
// header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

//check user

if (isGet() && action() == 'getUsers') {
    try {
        $users = User::getAll();
        echo json_encode($users);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if (isGet() && action() == 'getDocs') {
    try {
        $docs = Docs::getAll();
        echo json_encode($docs);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}


if (isGet() && action() == 'getPrograms') {
    try {
        $programs = Program::getAll();
        echo json_encode($programs);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}



if (isGet() && action() == 'getResearchFile' && isset($_GET['fileid'])) {
    try {
        $file = Docs::getById($_GET['fileid']);
        $filePath = __DIR__ . '/uploads/research_files/' . $file['researchfile'];

            if (file_exists($filePath) && $filePath != '') {
                file_transfer($filePath);
            } else {
                echo json_encode(['error' => 'File not found']);
                exit();
            }
            
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    exit();
}


if (isGet() && action() == 'getJC3File' && isset($_GET['fileid'])) {
    try {
        $file = Docs::getById($_GET['fileid']);
        $filePath = __DIR__ . '/uploads/jc3_files/' . $file['jc3file'];

            if (file_exists($filePath) && $filePath != '') {
                file_transfer($filePath);
            } else {
                echo json_encode(['error' => 'File not found']);
                exit();
            }
        
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    exit();
}




function file_transfer($filePath) {
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit();
    } else {
        echo json_encode(['error' => 'File not found']);
        exit();
    }
}



function convertPDF_to_Blur($pdfFilePath) {
    $outputFilePath = __DIR__ . '/uploads/blurred_' . basename($pdfFilePath);
    $command = "pdftk $pdfFilePath output $outputFilePath owner_pw 1234 allow AllFeatures";
    exec($command, $output, $returnVar);
    if ($returnVar !== 0) {
        throw new Exception('Error converting PDF to blurred version: ' . implode("\n", $output));
    }
    return $outputFilePath;
}


if (isGet() && action() == 'getDocsByProgram') {
    try {
        $docsByProgram = Docs::getAllByProgram(getUser('department'));
        echo json_encode($docsByProgram);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}


#admin dashboard
if (isGet() && action() == 'getTotalUsers') {
    try {
        $users = User::getTotalUsers();
        echo json_encode($users);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if (isGet() && action() == 'getTotalDocs') {
    try {
        $docs = Docs::getTotalDocsPerYear();
        echo json_encode($docs);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}


if (isGet() && action() == 'getTotalAdviserDocs') {
    try {
        $docs = Docs::getTotalAdviserDocs();
        echo json_encode($docs);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}
                            
if (isGet() && action() == 'getCountAdvisersByDepartment') {
    try {
        $docs = Docs::getCountAdvisersByDepartment(getUser('department'));
        echo json_encode($docs);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}


if (isGet() && action() == 'getCountByDepartment') {
    try {
        $docs = User::getCountByDepartment(getUser('department'));
        echo json_encode($docs);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}




#chairperson dashboard
if (isGet() && action() == 'getTotalUsersChairperson') {
    try {
        $users = User::getTotalUsersChairperson(getUser('department'));
        echo json_encode($users);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if (isGet() && action() == 'getTotalDocsChairperson') {
    try {
        $docs = Docs::getTotalDocsPerYearChairperson(getUser('department'));
        echo json_encode($docs);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}



#student dashboard
if (isGet() && action() == 'getCountDocUploaded') {
    try {
        $users = Docs::getCountDocUploaded(getUser('id'));
        echo json_encode($users);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}
