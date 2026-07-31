<?php
if( !defined( 'ADMIN_PAGE' ) ){
    exit( 'Script by OpenSolution.org' );
}

function getTableColumns( $db, string $table ): array{
    $cols = [];

    // SQLite (Quick.CMS często działa na SQLite)
    $stmt = $db->prepare( "PRAGMA table_info('$table')" );
    $stmt->execute();

    while( $row = $stmt->fetch( PDO::FETCH_ASSOC ) ){
        if( isset( $row['name'] ) ){
            $cols[$row['name']] = true;
        }
    }

    return $cols;
}

// CSRF token dla tego formularza
if( session_status() === PHP_SESSION_ACTIVE ){
    if( empty( $_SESSION['csrf_users_form'] ) ){
        $_SESSION['csrf_users_form'] = bin2hex( random_bytes( 32 ) );
    }
}

// --------------------------------------------
// SAVE (POST)
// --------------------------------------------
if( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['id'] ) ){

    $id = filter_var( $_POST['id'], FILTER_VALIDATE_INT );
    if( !$id || $id <= 0 ){
        header( 'Location: '.$config['admin_file'].'?p=users&sOption=error' );
        exit;
    }

    // CSRF check
    if( session_status() === PHP_SESSION_ACTIVE ){
        $tokenPost = (string) ( $_POST['_csrf'] ?? '' );
        $tokenSess = (string) ( $_SESSION['csrf_users_form'] ?? '' );

        if( empty( $tokenPost ) || empty( $tokenSess ) || !hash_equals( $tokenSess, $tokenPost ) ){
            header( 'Location: '.$config['admin_file'].'?p=users&sOption=csrf_error' );
            exit;
        }
    }

    // Zbieramy dane z POST (whitelist)
    $allowedFields = [
        'is_active',
        'name',
        'email',
        'phone',
        'street',
        'city',
        'code',
    ];

    // Jeśli tabela ma inną strukturę – aktualizujemy tylko istniejące kolumny
    $tableCols = getTableColumns( $oSql, 'users' );

    $data = [];

    // is_active (wymuszamy int + pilnujemy, że istnieje w configu)
    if( isset( $_POST['is_active'] ) ){
        $isActive = (int) $_POST['is_active'];

        if( isset( $config['user_status'] ) && is_array( $config['user_status'] ) ){
            if( !array_key_exists( $isActive, $config['user_status'] ) ){
                $isActive = 0;
            }
        }

        $data['is_active'] = $isActive;
    }

    // reszta pól: trim + limit długości
    $limits = [
        'name'   => 255,
        'email'  => 255,
        'phone'  => 50,
        'street' => 255,
        'city'   => 255,
        'code'   => 20,
    ];

    foreach( $limits as $key => $maxLen ){
        if( isset( $_POST[$key] ) ){
            $val = trim( (string) $_POST[$key] );

            if( function_exists( 'mb_substr' ) ){
                $val = mb_substr( $val, 0, $maxLen );
            } else {
                $val = substr( $val, 0, $maxLen );
            }

            $data[$key] = $val;
        }
    }

    // Walidacja email (jeśli podany)
    if( isset( $data['email'] ) && $data['email'] !== '' ){
        if( !filter_var( $data['email'], FILTER_VALIDATE_EMAIL ) ){
            $p = $_GET['p'] ?? 'users';
            header( 'Location: '.$config['admin_file'].'?p='.$p.'&id='.$id.'&sOption=email_error' );
            exit;
        }
    }

    // Budujemy UPDATE tylko z pól, które:
    // - są na whitelist
    // - istnieją w tabeli
    // - faktycznie przyszły w POST
    $set = [];
    $params = [ ':id' => $id ];

    foreach( $allowedFields as $field ){
        if( array_key_exists( $field, $data ) && isset( $tableCols[$field] ) ){
            $set[] = $field.' = :'.$field;
            $params[':'.$field] = $data[$field];
        }
    }

    if( !empty( $set ) ){
        $sql  = 'UPDATE users SET '.implode( ', ', $set ).' WHERE id = :id';
        $stmt = $oSql->prepare( $sql );
        $stmt->execute( $params );
    }

    // PRG redirect (żeby nie dublować zapisu na odświeżeniu)
    $p = $_GET['p'] ?? 'users';
    if( !preg_match( '/^[a-z0-9\-_]+$/i', $p ) ){
        $p = 'users';
    }

    header( 'Location: '.$config['admin_file'].'?p='.$p.'&sOption=save&id='.$id );
    exit;
}

// --------------------------------------------
// LOAD (GET)
// --------------------------------------------
$aData = [];
if( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ){
    $aData = $oPage->throwUserAdmin( (int) $_GET['id'] );
}

$sSelectedMenu = 'users';

require_once 'templates/admin/_header.php';
require_once 'templates/admin/_menu.php';
?>

<form action="?p=<?php echo html( (string) ( $_GET['p'] ?? 'users' ) ); ?><?php if( isset( $aData['id'] ) ) echo '&id='.(int) $aData['id']; ?>" name="form" method="post" class="main-form">

    <input type="hidden" name="id" id="id" value="<?php echo isset( $aData['id'] ) ? (int) $aData['id'] : 0; ?>" />

    <?php if( session_status() === PHP_SESSION_ACTIVE && !empty( $_SESSION['csrf_users_form'] ) ): ?>
        <input type="hidden" name="_csrf" value="<?php echo html( (string) $_SESSION['csrf_users_form'] ); ?>" />
    <?php endif; ?>

    <header class="mainPage__header mainPage__header_row">

        <h1 class="mainPage__title">Użytkownik - <?php echo html( (string) ( $aData['name'] ?? '' ) ); ?></h1>

        <div class="mainPage__buttons d-flex">
            <a href="?p=users" class="button button-light mr-2">Wróc do listy</a>
            <input type="submit" name="sOption" class="button button" value="<?php echo html( (string) $lang['save'] ); ?>" />
        </div>

    </header>

    <?php
    if( isset( $config['manual_link'] ) ){
        echo '<div class="manual"><a href="'.html( (string) $config['manual_link'] ).'instruction#users" title="'.html( (string) $lang['Help'] ).'" target="_blank"></a></div>';
    }

    $opt = $_GET['sOption'] ?? '';
    if( $opt === 'save' ){
        echo '<div class="alert alert-success">'.html( (string) $lang['Operation_completed'] ).'</div>';
    } elseif( $opt === 'email_error' ){
        echo '<div class="alert alert-danger">Błędny adres e-mail.</div>';
    } elseif( $opt === 'csrf_error' ){
        echo '<div class="alert alert-danger">Błąd bezpieczeństwa formularza (CSRF). Odśwież stronę i spróbuj ponownie.</div>';
    }
    ?>

    <ul class="tabs">
        <li id="content" class="selected"><a href="#content">Informacje</a></li>
    </ul>

    <script>
        $(function () {
            if (typeof displayTabInit === "function") displayTabInit();
            $(".main-form").quickform();
        });
    </script>

    <div id="tab-content" class="forms full tabsContent">

        <table class="list pages table mb-4" cellpadding="0" cellspacing="0" border="0">
            <tbody>
                <tr>
                    <td>ID</td>
                    <td><?php echo isset( $aData['id'] ) ? (int) $aData['id'] : 0; ?></td>
                </tr>

                <tr>
                    <td>Data rejestracji</td>
                    <td>
                        <?php
                        echo !empty( $aData['created_at'] )
                            ? html( date( "H:i, d.m.Y", strtotime( $aData['created_at'] ) ) )
                            : '-';
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Ostatnie logowanie</td>
                    <td>
                        <?php
                        echo !empty( $aData['last_login_at'] )
                            ? html( date( "H:i, d.m.Y", strtotime( $aData['last_login_at'] ) ) )
                            : '-';
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <ul>
            <li class="form-item">
                <label for="is_active">Status</label>
                <select name="is_active" id="is_active">
                    <?php echo getSelectFromArray( $config['user_status'], isset( $aData['is_active'] ) ? $aData['is_active'] : 0 ); ?>
                </select>
            </li>

            <li class="form-item">
                <label for="name">Imię i nazwisko</label>
                <input type="text" name="name" id="name" value="<?php echo html( (string) ( $aData['name'] ?? '' ) ); ?>" />
            </li>

            <li class="form-item">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?php echo html( (string) ( $aData['email'] ?? '' ) ); ?>" />
            </li>

            <li class="form-item">
                <label for="phone">Telefon</label>
                <input type="text" name="phone" id="phone" value="<?php echo html( (string) ( $aData['phone'] ?? '' ) ); ?>" />
            </li>

            <li class="form-item">
                <label for="street">Ulica</label>
                <input type="text" name="street" id="street" value="<?php echo html( (string) ( $aData['street'] ?? '' ) ); ?>" />
            </li>

            <li class="form-item">
                <label for="city">Miejscowość</label>
                <input type="text" name="city" id="city" value="<?php echo html( (string) ( $aData['city'] ?? '' ) ); ?>" />
            </li>

            <li class="form-item">
                <label for="code">Kod pocztowy</label>
                <input type="text" name="code" id="code" value="<?php echo html( (string) ( $aData['code'] ?? '' ) ); ?>" />
            </li>
        </ul>

    </div>

</form>

<?php
require_once 'templates/admin/_footer.php';
?>
