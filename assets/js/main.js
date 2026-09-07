(function ($) {
  "use strict";
  
  // ==========================================
  //      Start Document Ready function
  // ==========================================
  $(document).ready(function () {
    
    
  // =========================== Dropdown menu Js Start =======================
    $('.dropdown-menu').on('click', function (event) {
      event.stopPropagation(); 
    }); 

    // Remove Dropdown Menu
    $('.close-dropdown').on('click', function () {
      $('.dropdown-menu').removeClass('show'); 
      $('.dropdown-btn').removeClass('show'); 
      $('.dropdown-btn').setAttribute('aria-expanded', 'false')
    }); 
  // =========================== Dropdown menu Js End =======================


  // =========================== Submenu Open & Close Js Start =======================
  $('.has-dropdown > .sidebar-menu__link').on('click', function (event) {
    event.preventDefault();
    const currentItem = $(this).closest('.has-dropdown');

    $('.has-dropdown').not(currentItem).removeClass('activePage');
    $('.has-dropdown').not(currentItem).children('.sidebar-submenu').stop(true, true).slideUp(250);

    currentItem.toggleClass('activePage');
    currentItem.children('.sidebar-submenu').stop(true, true).slideToggle(250);
  }); 

  // $('.sidebar-menu__item.activePage').find('.sidebar-submenu').slideDown(400);
  // =========================== Submenu Open & Close Js End =======================
    
  
  // ========================== add active class to ul>li top Active current page Js Start =====================
  function dynamicActiveMenuClass(selector) {
    const currentPath = window.location.pathname.replace(/\/+$/, "");
    const currentFile = currentPath.split("/").pop();

    selector.find("a").each(function () {
      const href = $(this).attr("href");
      if (!href || href === "#" || href.toLowerCase().startsWith("javascript:")) {
        return;
      }

      const linkUrl = new URL(href, window.location.origin);
      const linkPath = linkUrl.pathname.replace(/\/+$/, "");
      const linkFile = linkPath.split("/").pop();

      if (linkPath === currentPath || linkFile === currentFile) {
        $(this).closest("li").addClass("activePage");
      }
    });

    // if any li has activePage element add class
    selector.children("li").each(function () {
      if ($(this).find(".activePage").length) {
        $(this).addClass("activePage");
      }
    });

    // if no file name return
    if (currentFile === "") {
      selector.find("li").eq(0).addClass("activePage");
    }
  }
  if ($(".sidebar-menu").length) {
    dynamicActiveMenuClass($(".sidebar-menu"));
  }
  // ========================== add active class to ul>li top Active current page Js End =====================
  

  //  =========================== Submenu Open & Active Dropdown menu while page active ========================
    if ($('.sidebar-menu__item').hasClass('activePage')) {
      $('.sidebar-menu__item.activePage').children('.sidebar-submenu').show(); 
    }
  //  =========================== Submenu Open & Active Dropdown menu while page active End ========================

  // ========================= Auto-Fit Dense Tables Start ===========================
  function applyAutoFitTableDensity() {
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth;

    $('.table-auto-fit').each(function () {
      const table = $(this);
      const headerColumns = table.find('thead th').length;
      const bodyColumns = table.find('tbody tr:first td').length;
      const columnCount = headerColumns || bodyColumns || 0;

      table.removeClass('table-fit-dense table-fit-ultra');

      if (columnCount >= 12 || (columnCount >= 10 && viewportWidth <= 1366)) {
        table.addClass('table-fit-ultra');
      } else if (columnCount >= 9 || (columnCount >= 7 && viewportWidth <= 1200)) {
        table.addClass('table-fit-dense');
      }
    });
  }

  applyAutoFitTableDensity();
  $(window).on('resize', applyAutoFitTableDensity);
  // ========================= Auto-Fit Dense Tables End ===========================


  //  =========================== Sidebar Open & Close Start ===============================
  $('.toggle-btn').on('click', function () {
    $('.sidebar').addClass('active')
    $('.side-overlay').addClass('show')
  }); 

  $('.side-overlay, .sidebar-close-btn').on('click', function () {
    $('.side-overlay').removeClass('show')
    $('.sidebar').removeClass('active')
  }); 
  //  =========================== Sidebar Open & Close End ===============================

  
  // =========================== Tooltip Js Start ===============================
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
  const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
  // =========================== Tooltip Js End ===============================

  // ============================= Image Upload Js Start ==============================
  $(function(){ 
    $("#fileUpload").fileUpload();
  });
  // ============================= Image Upload Js End ==============================

  
  // ============================= Image Upload Js Start ==============================
  $('.text-counter').on('input', function () {
    const characterCount = $(this).val().length; 
    console.log(characterCount);
    $('#current').text(characterCount); 
  }); 
  // ============================= Image Upload Js End ==============================

  
  // ============================= Course Details Accordion Js Start ==============================
  $('.course-item__button').on('click', function () {
    
    $('.course-item__button').not($(this)).removeClass('active'); 
    $('.course-item__button').not($(this)).closest('.course-item').find('.course-item-dropdown').slideUp(); 

    $(this).toggleClass('active'); 
    $(this).closest('.course-item').find('.course-item-dropdown').slideToggle(); 
  }); 

  $('.course-list__item.active .circle i').removeClass('ph ph-circle'); 
  $('.course-list__item.active .circle i').addClass('ph-fill ph-check-circle text-main-600'); 

  // ============================= Course Details Accordion Js End ==============================


  // ================== Password Show Hide Js Start ==========
  $(".toggle-password").on('click', function() {
    $(this).toggleClass("active");
    var input = $($(this).attr("id"));
    if (input.attr("type") == "password") {
      input.attr("type", "text");
    } else {
      input.attr("type", "password");
    }
  });
  // ========================= Password Show Hide Js End ===========================

  // ========================= Billing Radio Checked Js Start ===========================
  $('.form-check-input.payment-method-one').on('change', function () {
    $('.payment-method.payment-method-one').removeClass('active'); 
    $(this).closest('.payment-method.payment-method-one').addClass('active'); 
  }); 

  $('.form-check-input.payment-method-two').on('change', function () {
    $('.payment-method.payment-method-two').removeClass('active'); 
    $(this).closest('.payment-method.payment-method-two').addClass('active'); 
  }); 
  // ========================= Billing Radio Checked Js End ===========================

  
  // ========================= List Grid View Js Start ===========================
  $('.list-grid-view li.activePage').each(function() {
    var icon = $(this).find('a i');
    if (icon.hasClass('ph-rows')) {
        icon.removeClass('ph-rows').addClass('ph-fill ph-rows');
    } else if (icon.hasClass('ph-squares-four')) {
        icon.removeClass('ph-squares-four').addClass('ph-fill ph-squares-four');
    }
  });

  $('.list-view-btn').on('click', function () {
    $(this).addClass('active');
    $('.grid-view-btn').removeClass('active'); 
    $('.list-view').removeClass('d-none'); 
    $('.grid-view').addClass('d-none'); 
  }); 

  $('.grid-view-btn').on('click', function () {
    $(this).addClass('active');
    $('.list-view-btn').removeClass('active'); 
    $('.grid-view').removeClass('d-none'); 
    $('.list-view').addClass('d-none'); 
  }); 
  // ========================= List Grid View Js End ===========================

  
  // ========================= Toggle Search Box Js Start ===========================
  $('.toggle-search-btn').on('click', function () {
    $(this).toggleClass('bg-main-600 border-main-600 text-white'); 
    $('.toggle-search-box').slideToggle(); 
  }); 
  // ========================= Toggle Search Box Js End ===========================

  });
  // ==========================================
  //      End Document Ready function
  // ==========================================

  // ========================= Preloader Js Start =====================
    $(window).on("load", function(){
      $('.preloader').fadeOut(); 
    })
    // ========================= Preloader Js End=====================

    // ========================= Header Sticky Js Start ==============
    $(window).on('scroll', function() {
      if ($(window).scrollTop() >= 260) {
        $('.header').addClass('fixed-header');
      }
      else {
          $('.header').removeClass('fixed-header');
      }
    }); 
    // ========================= Header Sticky Js End===================

})(jQuery);
