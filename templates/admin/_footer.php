<?php
if (!defined('ADMIN_PAGE')) {
    exit;
}
?>

</div>

<footer class="mainFooter">
    <div class="container">
        <div class="mainFooter__content">

            <ul class="mainFooter__menu mobile-hide">
                <li class="menu_item">
                    <a href="http://opensolution.org" target="_blank" rel="noopener noreferrer" class="menu_link">OpenSolution.org</a>
                </li>
                <li class="menu_item">
                    <a href="http://opensolution.org/?p=support" target="_blank" rel="noopener noreferrer" class="menu_link"><?php echo $lang['Support']; ?></a>
                </li>
                <li class="menu_item">
                    <a href="<?php echo $config['manual_link']; ?>start" target="_blank" rel="noopener noreferrer" class="menu_link"><?php echo $lang['Manual']; ?></a>
                </li>
                <li class="menu_item">
                    <a href="http://opensolution.org/?p=licenses" target="_blank" rel="noopener noreferrer" class="menu_link"><?php echo $lang['License']; ?></a>
                </li>
                <li class="menu_item">
                    <a href="?p=languages" class="submenu_link menu_link"><?php echo $lang['Languages']; ?></a>
                </li>
            </ul>

            <div class="mainFooter__info">
                <div class="copy">
                    Copyright <?php echo date('Y'); ?> ©
                    <a href="https://kosa-x.com" target="_blank" rel="noopener noreferrer">KOSA X</a>
                </div>
            </div>

        </div>
    </div>
</footer>

</main>

<?php
if (
    isset($_COOKIE['bLicense' . str_replace('.', '', $config['version'])]) &&
    !isset($_COOKIE['bNoticesDisplayed']) &&
    isset($_SESSION['iMessagesNoticesNumber']) &&
    $_SESSION['iMessagesNoticesNumber'] > 0
) {
    ?>
    <script>
        $(function () {
            $('#messages .notices > a:first-child').trigger('click');
            createCookie('bNoticesDisplayed', 1);
        });
    </script>
    <?php
}
?>

<script src="plugins/chosen/chosen.jquery.min.js"></script>
<script src="core/libraries/quick.form.min.js"></script>
<script src="core/libraries/quick.box.min.js"></script>

</body>
</html>
