    </div> <!-- End container-fluid -->
</div> <!-- End main-content (opened in navbar.php) -->
</div> <!-- End wrapper (opened in header.php) -->

<!-- Core Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Elegant Sidebar Toggle Functionality
    $('#sidebarToggle').on('click', function(e) {
        e.preventDefault();
        
        let $sidebar = $('#sidebar');
        let $mainContent = $('#mainContent');
        
        // Handle Mobile vs Desktop
        if ($(window).width() <= 768) {
            $sidebar.toggleClass('mobile-open');
        } else {
            $sidebar.toggleClass('collapsed');
            // Rotate icon or any other animation if needed
            $(this).find('i').toggleClass('fa-bars-staggered fa-chevron-right');
        }
    });

    // Auto-close sidebar on mobile when clicking main content
    $('.main-content').on('click', function(e) {
        let $sidebar = $('#sidebar');
        if ($(window).width() <= 768 && $sidebar.hasClass('mobile-open')) {
            if ($(e.target).closest('#sidebarToggle').length === 0) {
                $sidebar.removeClass('mobile-open');
            }
        }
    });

    // Flash Message auto-dismiss
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
});
</script>

</body>
</html>
