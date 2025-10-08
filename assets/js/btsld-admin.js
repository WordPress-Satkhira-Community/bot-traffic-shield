jQuery(document).ready(function($) {
    'use strict';

    function activateTab(tabLink) {
        // Deactivate all tabs and content
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $('.btsld-tab-content').removeClass('active');

        // Activate the clicked tab and its content
        $(tabLink).addClass('nav-tab-active');
        $($(tabLink).attr('href')).addClass('active');

        // Store the active tab in localStorage
        if (window.localStorage) {
            localStorage.setItem('btsldActiveTab', $(tabLink).attr('href'));
        }
    }

    // Check localStorage for a previously active tab
    var activeTab = '#settings'; // Default tab
    if (window.localStorage && localStorage.getItem('btsldActiveTab')) {
        activeTab = localStorage.getItem('btsldActiveTab');
    }
    
    // Activate the stored or default tab on page load
    activateTab($('.nav-tab-wrapper a[href="' + activeTab + '"]'));

    // Handle tab clicks
    $('.nav-tab-wrapper a').click(function(e) {
        e.preventDefault();
        activateTab(this);
    });
});