<?php // views/layouts/footer.php ?>
  </div><!-- #ct-content -->
</div><!-- #ct-main -->
</div><!-- #ct-sidebar-wrap -->
<script>
// Flash message auto-dismiss
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.ct-alert[data-dismiss]').forEach(el => {
    setTimeout(() => el.style.opacity = '0', 3500);
  });
});
</script>
</body>
</html>
