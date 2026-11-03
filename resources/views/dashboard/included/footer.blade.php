<footer class="app-footer d-flex justify-content-between flex-wrap gap-2">
    <span>© {{ now()->year }} {{ config('app.name') }} — جميع الحقوق محفوظة</span>
    <span class="ltr">{{ now()->timezone(config('app.user_timezone', config('app.timezone')))->format('Y-m-d H:i') }}</span>
</footer>
