<?php
require_once dirname(__DIR__) . '/_bootstrap_admin.php';

use DevFramework\Core\Mail\Mailer;

global $OUTPUT, $currentUser;

// Collect flashes locally (not using admin_flash to keep it self-contained on one page render)
$messages = [];

$defaults = [
    'to' => method_exists($currentUser, 'getEmail') ? ($currentUser->getEmail() ?? '') : '',
    'subject' => 'DevFramework Test Email',
    'body' => "This is a test email generated at " . date('c') . "\n\nEnvironment: " . (config('app.env','unknown')),
    'format' => 'text',
    'attach' => false,
];

$input = [
    'to' => $_POST['to'] ?? $defaults['to'],
    'subject' => $_POST['subject'] ?? $defaults['subject'],
    'body' => $_POST['body'] ?? $defaults['body'],
    'format' => $_POST['format'] ?? $defaults['format'],
    'attach' => isset($_POST['attach']) && $_POST['attach'] === '1',
];

$sendResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!admin_csrf_validate()) {
        $messages[] = ['type'=>'error','text'=>'Invalid CSRF token. Please retry.'];
    } else {
        // Basic validation
        if (!$input['to'] || !filter_var($input['to'], FILTER_VALIDATE_EMAIL)) {
            $messages[] = ['type'=>'error','text'=>'Recipient email is invalid.'];
        } else if (!$input['subject']) {
            $messages[] = ['type'=>'error','text'=>'Subject is required.'];
        } else {
            try {
                $mailer = mailer();
                $msg = $mailer->newMessage()
                    ->to($input['to'])
                    ->subject($input['subject']);
                if ($input['format'] === 'html') {
                    $msg->html($input['body']);
                    // Provide a plain text alternative automatically
                    if (!str_contains(strip_tags($input['body']), ' ')) {
                        $msg->text(strip_tags($input['body']));
                    }
                } else {
                    $msg->text($input['body']);
                }
                if ($input['attach']) {
                    $sample = "Sample attachment generated " . date('c') . "\nSubject: {$input['subject']}\n";
                    $msg->attach($sample, 'sample.txt', 'text/plain', true);
                }
                $sendResult = $mailer->send($msg);
                if ($sendResult['success']) {
                    $messages[] = ['type'=>'success','text'=>'Email sent successfully via driver "' . $mailer->getDriverName() . '".'];
                } else {
                    $messages[] = ['type'=>'error','text'=>'Send failed: ' . ($sendResult['error'] ?? 'unknown error')];
                }
            } catch (Throwable $e) {
                $messages[] = ['type'=>'error','text'=>'Exception: ' . $e->getMessage()];
            }
        }
    }
}

// Prepare view flashes
$flashes = array_map(function($m){
    return [
        'text'=>$m['text'],
        'class' => $m['type']==='success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'
    ];
}, $messages);

$context = [
    'home_link' => '../index.php',
    'csrf_token' => admin_csrf_token(),
    'to' => htmlspecialchars($input['to'] ?? '', ENT_QUOTES),
    'subject' => htmlspecialchars($input['subject'] ?? '', ENT_QUOTES),
    'body' => htmlspecialchars($input['body'] ?? '', ENT_QUOTES),
    'is_html' => $input['format'] === 'html',
    'is_text' => $input['format'] === 'text',
    'attach_checked' => $input['attach'],
    'has_messages' => count($flashes) > 0,
    'messages' => $flashes,
    'driver' => function_exists('mailer') ? mailer()->getDriverName() : 'unknown'
];

echo $OUTPUT->header([
    'page_title' => 'Test Email',
    'site_name' => 'Admin Console',
    'user' => ['username'=>$currentUser->getUsername()],
]);
echo $OUTPUT->renderFromTemplate('admin_test_email', $context);
echo $OUTPUT->footer();
