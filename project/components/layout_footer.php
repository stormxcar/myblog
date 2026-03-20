   <?php include '../components/footer.php'; ?>

   <!-- Page-specific scripts -->
   <?php if (isset($page_scripts)): ?>
       <script>
           <?= $page_scripts; ?>
       </script>
   <?php endif; ?>

   </body>

   </html>