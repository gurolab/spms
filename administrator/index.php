<?php

require_once __DIR__ . '/../core/helper.php';

if (isLoggedIn()) {
    if(isAdmin()){
        redirect(BASE_URL.'/administrator/dashboard.php');
    }elseif(isSupplyOfficer()){
        redirect(BASE_URL.'/supplyofficer/index.php');
    }elseif(isInventoryOfficer()){
        redirect(BASE_URL.'/inventoryofficer/dashboard.php');
    }elseif(isPropertyCustodian()){
        redirect(BASE_URL.'/propertycustodian/dashboard.php');
    }elseif(isAuditor()){
        redirect(BASE_URL.'/auditor/dashboard.php');
    }elseif(isEmployee()){
        redirect(BASE_URL.'/employee/dashboard.php');
    }else{
        redirect(BASE_URL.'/unathorized.php');
    }

    
}else{
    flash('error', 'You must be logged in to access this page.');
    redirect(BASE_URL.'login.php');
}
