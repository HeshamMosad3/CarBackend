<?php

namespace App\Http\Controllers\Dashboard\Settings;

use App\Enums\AdminAlertType;
use App\Http\Controllers\Controller;
use App\Jobs\SendAdminAlertEmailJob;
use App\Models\Admin;
use App\Models\AdminNotificationEmail;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Who gets the "action needed" emails, and which kinds. Each address can be switched off entirely
 * and given its own list of alert types.
 */
class NotificationEmailController extends Controller
{
    public function index()
    {
        $emails = AdminNotificationEmail::oldest()->get();

        return view('dashboard.settings.notification-emails.index', [
            'emails' => $emails,
            'types' => AdminAlertType::options(),
            // Sign-in emails of the dashboard's own accounts that aren't recipients yet: one click to add.
            'suggestions' => Admin::query()
                ->whereNotNull('email')
                ->whereNotIn('email', $emails->pluck('email'))
                ->pluck('email'),
            'mailFrom' => config('mail.from.address'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:admin_notification_emails,email'],
            'label' => ['nullable', 'string', 'max:80'],
            'events' => ['nullable', 'array'],
            'events.*' => [Rule::in(AdminAlertType::keys())],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'هذا البريد مسجل مسبقاً.',
        ]);

        AdminNotificationEmail::create([
            'email' => $data['email'],
            'label' => $data['label'] ?? null,
            'is_active' => true,
            // Nothing ticked on the form means "everything", like every other new address.
            'events' => $data['events'] ?? AdminAlertType::keys(),
        ]);

        return redirect()->back()->with('success', 'تمت إضافة البريد. ستصله التنبيهات التي اخترتها.');
    }

    /** Switch an address on/off or change which alerts it receives (called as you click). */
    public function update(Request $request, $id)
    {
        $email = AdminNotificationEmail::findOrFail($id);

        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'events' => ['nullable', 'array'],
            'events.*' => [Rule::in(AdminAlertType::keys())],
            'events_sent' => ['nullable'],
        ]);

        if ($request->has('is_active')) {
            $email->is_active = $request->boolean('is_active');
        }
        // An empty list can't be sent as a form field, so the page says explicitly that it sent one.
        if ($request->has('events_sent')) {
            $email->events = array_values($data['events'] ?? []);
        }
        $email->save();

        return response()->json([
            'success' => true,
            'message' => $email->is_active ? 'تم الحفظ' : 'تم إيقاف التنبيهات لهذا البريد',
        ]);
    }

    /** Sends a real-looking alert right now, so the admin can confirm the address works. */
    public function test($id, EmailService $emailService)
    {
        $email = AdminNotificationEmail::findOrFail($id);

        $html = SendAdminAlertEmailJob::render(
            'رسالة تجريبية من لوحة التحكم',
            'إذا وصلتك هذه الرسالة فبريدك مضبوط لاستلام التنبيهات التي تحتاج إجراءً منك.',
            route('dashboard'),
            'info',
            'رسالة تجريبية',
            'فتح لوحة التحكم',
        );

        $sent = $emailService->sendEmail($email->email, 'رسالة تجريبية — تنبيهات لوحة التحكم', $html);

        return response()->json([
            'success' => $sent,
            'message' => $sent
                ? 'أُرسلت رسالة تجريبية إلى ' . $email->email
                : 'تعذّر الإرسال. تحقق من إعدادات البريد في الخادم (MAIL_*) ثم أعد المحاولة.',
        ]);
    }

    public function destroy($id)
    {
        AdminNotificationEmail::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'تم حذف البريد الإلكتروني بنجاح.');
    }
}
