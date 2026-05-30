# قوانین ProGuard برای دانش‌یار

# حفظ کلاس‌های اصلی اپلیکیشن
-keep class app.daneshyar.webview.** { *; }

# حفظ WebView JavaScript Interface
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# حفظ کلاس‌های Android
-keep class android.webkit.** { *; }
-keep class android.app.** { *; }
-keep class android.widget.** { *; }
-keep class android.view.** { *; }

# جلوگیری از حذف کدهای مهم
-keepattributes *Annotation*
-keepattributes SourceFile,LineNumberTable
-keepattributes JavascriptInterface

# بهینه‌سازی
-optimizations !code/simplification/arithmetic,!code/simplification/cast,!field/*,!class/merging/*
-optimizationpasses 5
-allowaccessmodification

# حذف لاگ‌ها در release
-assumenosideeffects class android.util.Log {
    public static *** d(...);
    public static *** v(...);
    public static *** i(...);
}
