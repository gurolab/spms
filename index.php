<?php

require_once __DIR__.'/core/helper.php';


if (isLoggedIn()) {
    if(isAdmin()){
        redirect('administrator/dashboard.php');
    }elseif(isSupplyOfficer()){
        redirect('supplyofficer/index.php');
    }elseif(isInventoryOfficer()){
        redirect('inventoryofficer/dashboard.php');
    }elseif(isPropertyCustodian()){
        redirect('propertycustodian/dashboard.php');
    }elseif(isAuditor()){
        redirect('auditor/dashboard.php');
    }elseif(isEmployee()){
        redirect('employee/dashboard.php');
    }else{
        redirect('/unathorized.php');
    }

    
}else{
    flash('error', 'You must be logged in to access this page.');
    redirect('login.php');
}