<?php
if (!defined('CUSTOMER_PAGE')) {
    exit;
}

$form_settings = [
    'contact' => [
        'messages' => [
//            'success' => 'Wiadomość została wysłana. Dziękujemy! 🙌',
        ],
        'email' => [
//            'send_to'  => [ $config['email'] ?? 'biuro@twojadomena.pl' ],
            'subject'  => 'Kontakt – '.($config['logo'] ?? 'Strona'),
            'copy_to_user' => true,
            'attachments'  => ['attachment'],
        ],
        'security' => [
            // jeśli chcesz, możesz nadpisać domyślne:
            'recaptcha_action' => 'contact_form',
        ],
        'fields' => [
            'name' => [
                'label'    => 'Imię i nazwisko',
                'required' => true,
                'type'     => 'text',
            ],
            'email' => [
                'label'    => 'E-mail',
                'required' => true,
                'type'     => 'email',
            ],
            'message' => [
                'label'    => 'Treść wiadomości',
                'required' => false,
                'type'     => 'textarea',
            ],
            'attachment' => [
                'label'         => 'Załącznik',
                'required'      => false,
                'type'          => 'file',
                'include_in_mail' => true,
            ],
            'regulamin' => [
                'label'    => 'Akceptuję warunki zawarte w polityce prywatności',
                'required' => true,
                'type'     => 'checkbox',
            ],
        ],
    ],
];

require_once 'plugins/form/form-handler.php';
require_once $theme.'_header.php';
require_once $theme.'_title.php';
?>

<div class="container pageContact">
    <div class="mainPage__wrapper">
        <div class="mainPage__content">

              

                 
        
           
           
            <div class="row pageContact__cards">
               
                <div class="col-6">
                   
                   <div class="card">
                       <ul class="list">
                           <li>
                               <div class="contactItem contactItem-phone">
                                   <div class="icon">
                                       <img src="<?php echo ICONS; ?>phone-line.svg" alt="<?php echo $lang['phone']; ?>" class="invert">
                                   </div>
                                   <div class="content">
                                       <span class="label"><?php echo $lang['phone']; ?></span>
                                       <a href="tel:<?php echo $config['phone']; ?>" class="value"><?php echo $config['phone']; ?></a>
                                   </div>
                                   <div class="action">
                                       <a href="" class="button button-light"><?php echo $lang['call']; ?></a>
                                   </div>
                               </div>
                           </li>
                           <?php if($config['phone2']): ?>
                           <li>
                               <div class="contactItem contactItem-phone">
                                   <div class="icon">
                                       <img src="<?php echo ICONS; ?>phone-line.svg" alt="<?php echo $lang['phone']; ?>" class="invert">
                                   </div>
                                   <div class="content">
                                       <span class="label"><?php echo $lang['phone']; ?></span>
                                       <a href="tel:<?php echo $config['phone']; ?>" class="value"><?php echo $config['phone']; ?></a>
                                   </div>
                                   <div class="action">
                                       <a href="" class="button button-light"><?php echo $lang['call']; ?></a>
                                   </div>
                               </div>
                           </li>
                           <?php endif; ?>
                           <li>
                               <div class="contactItem contactItem-email">
                                   <div class="icon">
                                       <img src="<?php echo ICONS; ?>email-line.svg" alt="<?php echo $lang['email']; ?>" class="invert">
                                   </div>
                                   <div class="content">
                                       <span class="label"><?php echo $lang['email']; ?></span>
                                       <a href="mailto:<?php echo $config['email']; ?>" class="value"><?php echo $config['email']; ?></a>
                                   </div>
                                   <div class="action">
                                       <a href="" class="button button-light"><?php echo $lang['write']; ?></a>
                                   </div>
                               </div>
                           </li>
                           <li>
                               <?php
                                if (!empty($aData['sDescriptionFull'])) {
                                    echo $aData['sDescriptionFull'];
                                }
                                ?>
                                <?php echo socialMedia(); ?>
                           </li>
                       </ul>
                   </div>
                   
        
                </div>
                   
                <div class="col-6">
                  
                  <div class="card">
                       <ul class="list">
                           <li>
                               <div class="contactItem contactItem-location">
                                   <div class="icon">
                                       <img src="<?php echo ICONS; ?>location-line.svg" alt="<?php echo $lang['location']; ?>" class="invert">
                                   </div>
                                   <div class="content">
                                       <span class="label"><?php echo $lang['location']; ?></span>
                                       <a href="<?php echo $config['maps']; ?>" target="_blank" class="value"><?php echo $config['street']; ?>, <?php echo $config['city']; ?> <?php echo $config['code']; ?></a>
                                   </div>

                               </div>
                           </li>

                           <li>
                               <?php echo hoursOpen(); ?>
                           </li>
                           <li>
                               <a href="" data-src="#map-widget" data-fancybox class="button button-light">Powiększ mapę</a>
                            <a href="<?php echo $config['maps']; ?>" target="_blank" class="button button-light">Nawigacja</a>
                           </li>
                       </ul>
                   </div>
                   
              
                </div>
     
                <div class="col-12">
                   
                    <div class="pageContact__form">
                        <div class="card">
                           <header class="card__header">
                                <h5 class="card__title">
                                    <img src="<?php echo ICONS; ?>send.svg" class="invert" alt="Wyślij wiadomość">
                                    Wyślij wiadomość
                                </h5>
                            </header>
                            <div class="card__content">
                           
                                
            <?php form_alert('contact'); ?>
            <?php if (!form_is_success('contact')): ?>

            <form action="" method="post" class="form pageContact__form" enctype="multipart/form-data">
                <input type="hidden" name="form_id" value="contact">
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                <input type="hidden" name="action" value="contact_form">

                <div class="row">
                    <div class="col-4">
                        <div class="form-item">
                            <input
                                type="text"
                                class="form-control<?php echo form_error_class('contact', 'name'); ?>"
                                id="name"
                                name="name"
                                value="<?php echo html(form_old('contact', 'name')); ?>"
                                placeholder="Imię i nazwisko"
                                required
                            >
                            <label for="name">Imię i nazwisko <span class="text-danger">*</span></label>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-item">
                            <input
                                type="email"
                                class="form-control<?php echo form_error_class('contact', 'email'); ?>"
                                id="email"
                                name="email"
                                value="<?php echo html(form_old('contact', 'email')); ?>"
                                placeholder="Adres e-mail"
                                required
                            >
                            <label for="email">E-mail <span class="text-danger">*</span></label>
                        </div>
                    </div>
                     <div class="col-4">
                        <div class="form-item">
                            <input
                                type="phone"
                                class="form-control<?php echo form_error_class('contact', 'phone'); ?>"
                                id="phone"
                                name="phone"
                                value="<?php echo html(form_old('contact', 'phone')); ?>"
                                placeholder="Telefon"
                                required
                            >
                            <label for="phone">Telefon <span class="text-danger">*</span></label>
                        </div>
                    </div>
                </div>

                <div class="form-item">
                   <textarea
                name="message"
                class="form-control<?php echo form_error_class('contact', 'message'); ?>"
                rows="4"
                id="message"
                placeholder="Treść wiadomości"
            ><?php echo html(form_old('contact', 'message')); ?></textarea>
                    <label for="message" class="col-form-label">Treść wiadomości</label>
                </div>

                <div class="form-item form-file mb-3">
                    <label for="attachment" class="form-label">Załącz plik (opcjonalnie)</label>
                    <input type="file" name="attachment" id="attachment" class="form-file">
                    <small class="text-muted form-text">
                        Dozwolone: <?php echo html(implode(', ', $form_defaults['security']['upload_allowed_ext'])); ?>.
                        Maks: <?php echo (int)($form_defaults['security']['upload_max_size']/1024/1024); ?> MB
                    </small>
                </div>

                <div class="form-check form-item mb-3">
                    <input
                class="form-check-input<?php echo form_error_class('contact', 'regulamin'); ?>"
                type="checkbox"
                value="1"
                id="regulamin"
                name="regulamin"
                <?php echo form_old('contact', 'regulamin') ? 'checked' : ''; ?>
                required
            >
                    <label class="form-check-label" for="regulamin">
                        <span class="text-danger">*</span> Akceptuję warunki zawarte w
                        <a href="<?php echo html(getUrl($config['private_policy'])); ?>" class="link_underline" target="_blank">
                            <?php echo html(getData($config['private_policy'], 'sName')); ?>
                        </a>.
                    </label>
                </div>

                <div class="form-button">
                    <button type="submit" id="submit" name="submit" class="button">
                        Wyślij
                    </button>
                </div>
            </form>

            <?php endif; ?>

                           
                            </div>
                        </div>
                    </div>
                </div>
            </div>

       

            <!-- Pliki do pobrania -->
            <?php echo $oFile->listFiles($aData['iPage']); ?>

        </div>
    </div>

    <?php
    // lista powiązanych stron / podstron
    echo $oPage->listPages($aData['iPage'], [
        'hide_cat' => true,
        'class'    => 'pagesList-'.$aData['iPage'],
    ]);
    ?>
</div>


<?php if ($config['publicKey']): ?>
  <script src="https://www.google.com/recaptcha/api.js?render=<?php echo html($config['publicKey']); ?>"></script>
  <script>
    grecaptcha.ready(function() {
      grecaptcha.execute('<?php echo html($config['publicKey']); ?>', {action: 'contact_form'}).then(function(token) {
        var el = document.getElementById('g-recaptcha-response');
        if (el) el.value = token;
      });
    });
  </script>
<?php endif; ?>


<div id="map-widget" style="display:none" data-selectable="true">
   <div class="card">
        <header class="card__header">
            <h5 class="card__title">
                <img src="<?= ICONS; ?>location.svg" alt="">
                Lokalizacja na mapie
            </h5>
        </header>
        <div class="card__wrapper">
            <iframe
            src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d2541.1633581155897!2d23.4257508!3d50.4380578!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4724afb806ed7e8b%3A0xd453297b44919eeb!2sOKR%C4%98GOWA%20STACJA%20KONTROLI%20POJAZD%C3%93W!5e0!3m2!1spl!2spl!4v1760983044148!5m2!1spl!2spl"
            width="100%"
            style="border:0;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
</div>

<?php require_once $theme.'_footer.php'; ?>
