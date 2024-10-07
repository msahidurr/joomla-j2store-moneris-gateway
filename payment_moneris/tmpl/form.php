<?php
/*------------------------------------------------------------------------
# com_j2store - J2Store
# ------------------------------------------------------------------------
# author    Sasi varna kumar - Weblogicx India http://www.weblogicxindia.com
# copyright Copyright (C) 2014 - 19 Weblogicxindia.com. All Rights Reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://j2store.org
# Technical Support:  Forum - http://j2store.org/forum/index.html
-------------------------------------------------------------------------*/


defined('_JEXEC') or die('Restricted access'); ?>

<?php echo JText::_($vars->onselection_text); ?>

<script>
    document.querySelectorAll("#onCheckoutPayment_wrapper label img").forEach(function(img) {
    img.setAttribute('width', '100');
});

document.querySelectorAll("#onCheckoutPayment_wrapper label font").forEach(function(font) {
    font.remove();
});

document.querySelectorAll("#onCheckoutPayment_wrapper label font").forEach(function(font) {
    font.style.display = 'none';
});

document.querySelector("#onCheckoutPayment_wrapper label").childNodes.forEach(function(node) {
    if (node.nodeType === 3) { // Check if it's a text node
        node.textContent = ''; // Clear the text node content
    }
});
</script>