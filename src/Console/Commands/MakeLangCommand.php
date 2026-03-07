<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Language Translation Files Command
|--------------------------------------------------------------------------
| Scaffolds translation files for a given locale with
| pre-filled common translation groups (messages, validation, auth).
*/

use Plugs\Console\Command;

class MakeLangCommand extends Command
{
    protected string $description = 'Create translation files for a new locale';

    protected function defineArguments(): array
    {
        return [
            'locale' => 'The locale code to generate (e.g., en, fr, es, de)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force, -f' => 'Overwrite existing translation files',
            '--groups' => 'Comma-separated list of groups to create (default: messages,validation,auth)',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Translation File Generator');

        $locale = $this->argument('0');

        if (!$locale) {
            $locale = $this->ask('Locale code (e.g., en, fr, es, de)', 'en');
        }

        $locale = strtolower(trim($locale));
        $force = $this->isForce();

        // Determine which groups to create
        $groupsOption = $this->option('groups');
        $groups = $groupsOption
            ? array_map('trim', explode(',', (string) $groupsOption))
            : ['messages', 'validation', 'auth'];

        $langPath = getcwd() . '/resources/lang/' . $locale;

        $this->section('Configuration');
        $this->keyValue('Locale', $locale);
        $this->keyValue('Groups', implode(', ', $groups));
        $this->keyValue('Path', "resources/lang/{$locale}/");
        $this->keyValue('Overwrite', $force ? 'Yes' : 'No');
        $this->newLine();

        $this->ensureDirectory($langPath);

        $created = 0;
        $skipped = 0;

        foreach ($groups as $group) {
            $filePath = "{$langPath}/{$group}.php";

            if (file_exists($filePath) && !$force) {
                $this->warning("  Skipped: {$group}.php (already exists, use --force to overwrite)");
                $skipped++;
                continue;
            }

            $content = $this->getGroupContent($group, $locale);

            $this->task("Creating {$group}.php", function () use ($filePath, $content) {
                $this->writeFile($filePath, $content);
            });

            $created++;
        }

        $this->checkpoint('finished');

        $this->newLine();
        $this->box(
            "Locale '{$locale}' translation files generated!\n\n" .
            "Created: {$created} file(s)\n" .
            "Skipped: {$skipped} file(s)\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            '✅ Success',
            'success'
        );

        $this->section('Usage');
        $this->bulletList([
            "Retrieve translations: echo __('messages.welcome');",
            "With placeholders: echo __('messages.greet', ['name' => 'Celio']);",
            "Switch locale at runtime: \$translator->setLocale('{$locale}');",
            "Or use ?lang={$locale} query parameter with LocalizationMiddleware",
        ]);
        $this->newLine();

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    private function getGroupContent(string $group, string $locale): string
    {
        return match ($group) {
            'messages' => $this->getMessagesContent($locale),
            'validation' => $this->getValidationContent($locale),
            'auth' => $this->getAuthContent($locale),
            default => $this->getEmptyGroupContent($group),
        };
    }

    private function getMessagesContent(string $locale): string
    {
        $translations = match ($locale) {
            'fr' => [
                'welcome' => 'Bienvenue dans notre application !',
                'greet' => 'Bonjour, :name !',
                'goodbye' => 'Au revoir, :name. À bientôt !',
                'login' => 'Se connecter',
                'logout' => 'Se déconnecter',
                'register' => "S'inscrire",
                'home' => 'Accueil',
                'dashboard' => 'Tableau de bord',
                'settings' => 'Paramètres',
                'profile' => 'Profil',
                'save' => 'Enregistrer',
                'cancel' => 'Annuler',
                'delete' => 'Supprimer',
                'edit' => 'Modifier',
                'create' => 'Créer',
                'update' => 'Mettre à jour',
                'search' => 'Rechercher',
                'loading' => 'Chargement...',
                'success' => 'Opération effectuée avec succès.',
                'error' => 'Une erreur est survenue. Veuillez réessayer.',
                'confirm_delete' => 'Êtes-vous sûr de vouloir supprimer ceci ?',
                'no_results' => 'Aucun résultat trouvé.',
                'back' => 'Retour',
                'next' => 'Suivant',
                'previous' => 'Précédent',
            ],
            'es' => [
                'welcome' => '¡Bienvenido a nuestra aplicación!',
                'greet' => '¡Hola, :name!',
                'goodbye' => '¡Adiós, :name. Hasta pronto!',
                'login' => 'Iniciar sesión',
                'logout' => 'Cerrar sesión',
                'register' => 'Registrarse',
                'home' => 'Inicio',
                'dashboard' => 'Panel de control',
                'settings' => 'Configuración',
                'profile' => 'Perfil',
                'save' => 'Guardar',
                'cancel' => 'Cancelar',
                'delete' => 'Eliminar',
                'edit' => 'Editar',
                'create' => 'Crear',
                'update' => 'Actualizar',
                'search' => 'Buscar',
                'loading' => 'Cargando...',
                'success' => 'Operación completada con éxito.',
                'error' => 'Ocurrió un error. Por favor, inténtelo de nuevo.',
                'confirm_delete' => '¿Está seguro de que desea eliminar esto?',
                'no_results' => 'No se encontraron resultados.',
                'back' => 'Atrás',
                'next' => 'Siguiente',
                'previous' => 'Anterior',
            ],
            'de' => [
                'welcome' => 'Willkommen in unserer Anwendung!',
                'greet' => 'Hallo, :name!',
                'goodbye' => 'Auf Wiedersehen, :name. Bis bald!',
                'login' => 'Anmelden',
                'logout' => 'Abmelden',
                'register' => 'Registrieren',
                'home' => 'Startseite',
                'dashboard' => 'Dashboard',
                'settings' => 'Einstellungen',
                'profile' => 'Profil',
                'save' => 'Speichern',
                'cancel' => 'Abbrechen',
                'delete' => 'Löschen',
                'edit' => 'Bearbeiten',
                'create' => 'Erstellen',
                'update' => 'Aktualisieren',
                'search' => 'Suchen',
                'loading' => 'Laden...',
                'success' => 'Vorgang erfolgreich abgeschlossen.',
                'error' => 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
                'confirm_delete' => 'Sind Sie sicher, dass Sie dies löschen möchten?',
                'no_results' => 'Keine Ergebnisse gefunden.',
                'back' => 'Zurück',
                'next' => 'Weiter',
                'previous' => 'Zurück',
            ],
            'pt' => [
                'welcome' => 'Bem-vindo à nossa aplicação!',
                'greet' => 'Olá, :name!',
                'goodbye' => 'Adeus, :name. Até breve!',
                'login' => 'Entrar',
                'logout' => 'Sair',
                'register' => 'Registrar',
                'home' => 'Início',
                'dashboard' => 'Painel',
                'settings' => 'Configurações',
                'profile' => 'Perfil',
                'save' => 'Salvar',
                'cancel' => 'Cancelar',
                'delete' => 'Excluir',
                'edit' => 'Editar',
                'create' => 'Criar',
                'update' => 'Atualizar',
                'search' => 'Buscar',
                'loading' => 'Carregando...',
                'success' => 'Operação concluída com sucesso.',
                'error' => 'Ocorreu um erro. Por favor, tente novamente.',
                'confirm_delete' => 'Tem certeza de que deseja excluir isso?',
                'no_results' => 'Nenhum resultado encontrado.',
                'back' => 'Voltar',
                'next' => 'Próximo',
                'previous' => 'Anterior',
            ],
            'zh' => [
                'welcome' => '欢迎使用我们的应用！',
                'greet' => '你好, :name！',
                'goodbye' => '再见, :name。回头见！',
                'login' => '登录',
                'logout' => '退出',
                'register' => '注册',
                'home' => '首页',
                'dashboard' => '仪表盘',
                'settings' => '设置',
                'profile' => '个人资料',
                'save' => '保存',
                'cancel' => '取消',
                'delete' => '删除',
                'edit' => '编辑',
                'create' => '创建',
                'update' => '更新',
                'search' => '搜索',
                'loading' => '加载中...',
                'success' => '操作成功完成。',
                'error' => '发生错误，请重试。',
                'confirm_delete' => '您确定要删除吗？',
                'no_results' => '没有找到结果。',
                'back' => '返回',
                'next' => '下一页',
                'previous' => '上一页',
            ],
            'ar' => [
                'welcome' => 'مرحباً بكم في تطبيقنا!',
                'greet' => 'مرحباً, :name!',
                'goodbye' => 'مع السلامة, :name. نراك قريباً!',
                'login' => 'تسجيل الدخول',
                'logout' => 'تسجيل الخروج',
                'register' => 'التسجيل',
                'home' => 'الرئيسية',
                'dashboard' => 'لوحة التحكم',
                'settings' => 'الإعدادات',
                'profile' => 'الملف الشخصي',
                'save' => 'حفظ',
                'cancel' => 'إلغاء',
                'delete' => 'حذف',
                'edit' => 'تعديل',
                'create' => 'إنشاء',
                'update' => 'تحديث',
                'search' => 'بحث',
                'loading' => 'جاري التحميل...',
                'success' => 'تمت العملية بنجاح.',
                'error' => 'حدث خطأ. يرجى المحاولة مرة أخرى.',
                'confirm_delete' => 'هل أنت متأكد من أنك تريد حذف هذا؟',
                'no_results' => 'لم يتم العثور على نتائج.',
                'back' => 'رجوع',
                'next' => 'التالي',
                'previous' => 'السابق',
            ],
            default => [
                'welcome' => 'Welcome to our application!',
                'greet' => 'Hello, :name!',
                'goodbye' => 'Goodbye, :name. See you soon!',
                'login' => 'Log In',
                'logout' => 'Log Out',
                'register' => 'Register',
                'home' => 'Home',
                'dashboard' => 'Dashboard',
                'settings' => 'Settings',
                'profile' => 'Profile',
                'save' => 'Save',
                'cancel' => 'Cancel',
                'delete' => 'Delete',
                'edit' => 'Edit',
                'create' => 'Create',
                'update' => 'Update',
                'search' => 'Search',
                'loading' => 'Loading...',
                'success' => 'Operation completed successfully.',
                'error' => 'An error occurred. Please try again.',
                'confirm_delete' => 'Are you sure you want to delete this?',
                'no_results' => 'No results found.',
                'back' => 'Back',
                'next' => 'Next',
                'previous' => 'Previous',
            ],
        };

        return $this->arrayToPhpFile($translations);
    }

    private function getValidationContent(string $locale): string
    {
        $translations = match ($locale) {
            'fr' => [
                'required' => 'Le champ :attribute est obligatoire.',
                'email' => 'Le champ :attribute doit être une adresse email valide.',
                'min' => 'Le champ :attribute doit contenir au moins :min caractères.',
                'max' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
                'unique' => 'La valeur du champ :attribute est déjà utilisée.',
                'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
                'numeric' => 'Le champ :attribute doit être un nombre.',
                'string' => 'Le champ :attribute doit être une chaîne de caractères.',
                'url' => 'Le champ :attribute doit être une URL valide.',
                'between' => 'Le champ :attribute doit être compris entre :min et :max.',
                'in' => 'La valeur sélectionnée pour :attribute est invalide.',
                'date' => 'Le champ :attribute doit être une date valide.',
                'image' => 'Le champ :attribute doit être une image.',
                'file' => 'Le champ :attribute doit être un fichier.',
                'password' => 'Le mot de passe est incorrect.',
                'accepted' => 'Le champ :attribute doit être accepté.',
            ],
            'es' => [
                'required' => 'El campo :attribute es obligatorio.',
                'email' => 'El campo :attribute debe ser una dirección de correo electrónico válida.',
                'min' => 'El campo :attribute debe tener al menos :min caracteres.',
                'max' => 'El campo :attribute no debe exceder :max caracteres.',
                'unique' => 'El valor del campo :attribute ya ha sido tomado.',
                'confirmed' => 'La confirmación del campo :attribute no coincide.',
                'numeric' => 'El campo :attribute debe ser un número.',
                'string' => 'El campo :attribute debe ser una cadena de texto.',
                'url' => 'El campo :attribute debe ser una URL válida.',
                'between' => 'El campo :attribute debe estar entre :min y :max.',
                'in' => 'El valor seleccionado para :attribute es inválido.',
                'date' => 'El campo :attribute debe ser una fecha válida.',
                'image' => 'El campo :attribute debe ser una imagen.',
                'file' => 'El campo :attribute debe ser un archivo.',
                'password' => 'La contraseña es incorrecta.',
                'accepted' => 'El campo :attribute debe ser aceptado.',
            ],
            'de' => [
                'required' => 'Das Feld :attribute ist erforderlich.',
                'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
                'min' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
                'max' => 'Das Feld :attribute darf nicht mehr als :max Zeichen lang sein.',
                'unique' => 'Der Wert des Feldes :attribute wird bereits verwendet.',
                'confirmed' => 'Die Bestätigung des Feldes :attribute stimmt nicht überein.',
                'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
                'string' => 'Das Feld :attribute muss eine Zeichenkette sein.',
                'url' => 'Das Feld :attribute muss eine gültige URL sein.',
                'between' => 'Das Feld :attribute muss zwischen :min und :max liegen.',
                'in' => 'Der ausgewählte Wert für :attribute ist ungültig.',
                'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
                'image' => 'Das Feld :attribute muss ein Bild sein.',
                'file' => 'Das Feld :attribute muss eine Datei sein.',
                'password' => 'Das Passwort ist falsch.',
                'accepted' => 'Das Feld :attribute muss akzeptiert werden.',
            ],
            'pt' => [
                'required' => 'O campo :attribute é obrigatório.',
                'email' => 'O campo :attribute deve ser um endereço de email válido.',
                'min' => 'O campo :attribute deve ter pelo menos :min caracteres.',
                'max' => 'O campo :attribute não deve exceder :max caracteres.',
                'unique' => 'O valor do campo :attribute já está em uso.',
                'confirmed' => 'A confirmação do campo :attribute não corresponde.',
                'numeric' => 'O campo :attribute deve ser um número.',
                'string' => 'O campo :attribute deve ser uma string.',
                'url' => 'O campo :attribute deve ser uma URL válida.',
                'between' => 'O campo :attribute deve estar entre :min e :max.',
                'in' => 'O valor selecionado para :attribute é inválido.',
                'date' => 'O campo :attribute deve ser uma data válida.',
                'image' => 'O campo :attribute deve ser uma imagem.',
                'file' => 'O campo :attribute deve ser um arquivo.',
                'password' => 'A senha está incorreta.',
                'accepted' => 'O campo :attribute deve ser aceito.',
            ],
            'zh' => [
                'required' => ':attribute 字段是必填的。',
                'email' => ':attribute 必须是有效的电子邮件地址。',
                'min' => ':attribute 至少需要 :min 个字符。',
                'max' => ':attribute 不能超过 :max 个字符。',
                'unique' => ':attribute 已经被使用。',
                'confirmed' => ':attribute 确认不匹配。',
                'numeric' => ':attribute 必须是数字。',
                'string' => ':attribute 必须是字符串。',
                'url' => ':attribute 必须是有效的 URL。',
                'between' => ':attribute 必须在 :min 和 :max 之间。',
                'in' => '所选的 :attribute 无效。',
                'date' => ':attribute 必须是有效的日期。',
                'image' => ':attribute 必须是图片。',
                'file' => ':attribute 必须是文件。',
                'password' => '密码不正确。',
                'accepted' => ':attribute 必须被接受。',
            ],
            'ar' => [
                'required' => 'حقل :attribute مطلوب.',
                'email' => 'حقل :attribute يجب أن يكون عنوان بريد إلكتروني صالح.',
                'min' => 'حقل :attribute يجب أن يحتوي على الأقل :min حرف.',
                'max' => 'حقل :attribute يجب ألا يتجاوز :max حرف.',
                'unique' => 'قيمة حقل :attribute مستخدمة بالفعل.',
                'confirmed' => 'تأكيد حقل :attribute غير متطابق.',
                'numeric' => 'حقل :attribute يجب أن يكون رقماً.',
                'string' => 'حقل :attribute يجب أن يكون نصاً.',
                'url' => 'حقل :attribute يجب أن يكون رابط URL صالح.',
                'between' => 'حقل :attribute يجب أن يكون بين :min و :max.',
                'in' => 'القيمة المحددة لـ :attribute غير صالحة.',
                'date' => 'حقل :attribute يجب أن يكون تاريخاً صالحاً.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'file' => 'حقل :attribute يجب أن يكون ملفاً.',
                'password' => 'كلمة المرور غير صحيحة.',
                'accepted' => 'حقل :attribute يجب أن يُقبل.',
            ],
            default => [
                'required' => 'The :attribute field is required.',
                'email' => 'The :attribute must be a valid email address.',
                'min' => 'The :attribute must be at least :min characters.',
                'max' => 'The :attribute must not exceed :max characters.',
                'unique' => 'The :attribute has already been taken.',
                'confirmed' => 'The :attribute confirmation does not match.',
                'numeric' => 'The :attribute must be a number.',
                'string' => 'The :attribute must be a string.',
                'url' => 'The :attribute must be a valid URL.',
                'between' => 'The :attribute must be between :min and :max.',
                'in' => 'The selected :attribute is invalid.',
                'date' => 'The :attribute must be a valid date.',
                'image' => 'The :attribute must be an image.',
                'file' => 'The :attribute must be a file.',
                'password' => 'The password is incorrect.',
                'accepted' => 'The :attribute must be accepted.',
            ],
        };

        return $this->arrayToPhpFile($translations);
    }

    private function getAuthContent(string $locale): string
    {
        $translations = match ($locale) {
            'fr' => [
                'failed' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
                'throttle' => 'Trop de tentatives de connexion. Veuillez réessayer dans :seconds secondes.',
                'password' => 'Le mot de passe fourni est incorrect.',
                'token' => 'Ce jeton de réinitialisation du mot de passe est invalide.',
                'sent' => 'Nous vous avons envoyé un lien de réinitialisation du mot de passe par email.',
                'reset' => 'Votre mot de passe a été réinitialisé.',
                'verified' => 'Votre adresse email a été vérifiée.',
                'confirm' => 'Veuillez confirmer votre mot de passe avant de continuer.',
                'unauthorized' => "Vous n'êtes pas autorisé à accéder à cette ressource.",
                'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
            ],
            'es' => [
                'failed' => 'Estas credenciales no coinciden con nuestros registros.',
                'throttle' => 'Demasiados intentos de inicio de sesión. Por favor, inténtelo de nuevo en :seconds segundos.',
                'password' => 'La contraseña proporcionada es incorrecta.',
                'token' => 'Este token de restablecimiento de contraseña es inválido.',
                'sent' => 'Le hemos enviado un enlace para restablecer su contraseña por correo electrónico.',
                'reset' => 'Su contraseña ha sido restablecida.',
                'verified' => 'Su correo electrónico ha sido verificado.',
                'confirm' => 'Por favor, confirme su contraseña antes de continuar.',
                'unauthorized' => 'No está autorizado para acceder a este recurso.',
                'session_expired' => 'Su sesión ha expirado. Por favor, inicie sesión de nuevo.',
            ],
            'de' => [
                'failed' => 'Diese Anmeldedaten stimmen nicht mit unseren Aufzeichnungen überein.',
                'throttle' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :seconds Sekunden erneut.',
                'password' => 'Das angegebene Passwort ist falsch.',
                'token' => 'Dieses Token zum Zurücksetzen des Passworts ist ungültig.',
                'sent' => 'Wir haben Ihnen einen Link zum Zurücksetzen Ihres Passworts per E-Mail gesendet.',
                'reset' => 'Ihr Passwort wurde zurückgesetzt.',
                'verified' => 'Ihre E-Mail-Adresse wurde verifiziert.',
                'confirm' => 'Bitte bestätigen Sie Ihr Passwort, bevor Sie fortfahren.',
                'unauthorized' => 'Sie sind nicht berechtigt, auf diese Ressource zuzugreifen.',
                'session_expired' => 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.',
            ],
            'pt' => [
                'failed' => 'Essas credenciais não correspondem aos nossos registros.',
                'throttle' => 'Muitas tentativas de login. Por favor, tente novamente em :seconds segundos.',
                'password' => 'A senha fornecida está incorreta.',
                'token' => 'Este token de redefinição de senha é inválido.',
                'sent' => 'Enviamos um link para redefinir sua senha por email.',
                'reset' => 'Sua senha foi redefinida.',
                'verified' => 'Seu email foi verificado.',
                'confirm' => 'Por favor, confirme sua senha antes de continuar.',
                'unauthorized' => 'Você não está autorizado a acessar este recurso.',
                'session_expired' => 'Sua sessão expirou. Por favor, faça login novamente.',
            ],
            'zh' => [
                'failed' => '这些凭据与我们的记录不匹配。',
                'throttle' => '登录尝试次数过多。请在 :seconds 秒后重试。',
                'password' => '提供的密码不正确。',
                'token' => '此密码重置令牌无效。',
                'sent' => '我们已通过电子邮件发送了密码重置链接。',
                'reset' => '您的密码已重置。',
                'verified' => '您的电子邮件已验证。',
                'confirm' => '请在继续之前确认您的密码。',
                'unauthorized' => '您无权访问此资源。',
                'session_expired' => '您的会话已过期。请重新登录。',
            ],
            'ar' => [
                'failed' => 'بيانات الاعتماد هذه لا تتطابق مع سجلاتنا.',
                'throttle' => 'محاولات تسجيل دخول كثيرة جداً. يرجى المحاولة مرة أخرى بعد :seconds ثانية.',
                'password' => 'كلمة المرور المقدمة غير صحيحة.',
                'token' => 'رمز إعادة تعيين كلمة المرور هذا غير صالح.',
                'sent' => 'لقد أرسلنا رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.',
                'reset' => 'تم إعادة تعيين كلمة المرور الخاصة بك.',
                'verified' => 'تم التحقق من بريدك الإلكتروني.',
                'confirm' => 'يرجى تأكيد كلمة المرور الخاصة بك قبل المتابعة.',
                'unauthorized' => 'غير مصرح لك بالوصول إلى هذا المورد.',
                'session_expired' => 'انتهت صلاحية جلستك. يرجى تسجيل الدخول مرة أخرى.',
            ],
            default => [
                'failed' => 'These credentials do not match our records.',
                'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
                'password' => 'The provided password is incorrect.',
                'token' => 'This password reset token is invalid.',
                'sent' => 'We have emailed your password reset link.',
                'reset' => 'Your password has been reset.',
                'verified' => 'Your email has been verified.',
                'confirm' => 'Please confirm your password before continuing.',
                'unauthorized' => 'You are not authorized to access this resource.',
                'session_expired' => 'Your session has expired. Please log in again.',
            ],
        };

        return $this->arrayToPhpFile($translations);
    }

    private function getEmptyGroupContent(string $group): string
    {
        return "<?php\n\n// {$group} translations\nreturn [\n    //\n];\n";
    }

    private function arrayToPhpFile(array $translations): string
    {
        $lines = ["<?php\n", "return ["];

        foreach ($translations as $key => $value) {
            $escapedValue = str_replace("'", "\\'", $value);
            $lines[] = "    '{$key}' => '{$escapedValue}',";
        }

        $lines[] = "];\n";

        return implode("\n", $lines);
    }
}
