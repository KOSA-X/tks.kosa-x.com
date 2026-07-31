<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

if( isset( $_GET['iItemDelete'] ) && is_numeric( $_GET['iItemDelete'] ) ){
    $id = (int) $_GET['iItemDelete'];

    $oSql->query( 'DELETE FROM users WHERE id="'.$id.'"' );

    header( 'Location: '.$config['admin_file'].'?p=users&sOption=del' );
    exit;
}

$sSelectedMenu = 'users';

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';

$sUsersList = $oPage->listUsersAdmin();

if( !empty( $sUsersList ) ){
?>
    <form action="?p=users<?php if( isset( $_GET['sSort'] ) ) echo '&amp;sSort='.html( (string) $_GET['sSort'] ); ?>" name="form" method="post" class="main-form">

        <header class="mainPage__header">
            <h1 class="mainPage__title">Użytkownicy</h1>
        </header>

        <?php
        if( isset( $_GET['sOption'] ) ){
            echo '<div class="alert alert-success mb-3">'.$lang['Operation_completed'].'</div>';
        }
        ?>

        <div class="table-responsive">
            <table class="list pages table" cellpadding="0" cellspacing="0" border="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imię i nazwisko</th>
                        <th>Adres email</th>
                        <th>Data rejestracji</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $sUsersList; ?>
                </tbody>
            </table>
        </div>

    </form>
<?php
}
else{
    echo '<div class="alert alert-danger">'.$lang['Data_not_found'].'</div>';
}

require_once 'templates/admin/_footer.php';
?>
