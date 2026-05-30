package app.daneshyar.webview;

import android.animation.AnimatorSet;
import android.animation.ObjectAnimator;
import android.app.Activity;
import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.animation.DecelerateInterpolator;
import android.view.animation.OvershootInterpolator;
import android.widget.ImageView;
import android.widget.TextView;

/**
 * اسپلش اسکرین حرفه‌ای دانش‌یار
 * با انیمیشن‌های نرم و زیبا
 */
public class SplashActivity extends Activity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_splash);

        // پیدا کردن ویوها
        ImageView logo = findViewById(R.id.splash_logo);
        TextView title = findViewById(R.id.splash_title);
        TextView subtitle = findViewById(R.id.splash_subtitle);
        View loadingDots = findViewById(R.id.splash_loading);

        // انیمیشن لوگو: zoom in با bounce
        logo.setAlpha(0f);
        logo.setScaleX(0.3f);
        logo.setScaleY(0.3f);

        ObjectAnimator logoAlpha = ObjectAnimator.ofFloat(logo, "alpha", 0f, 1f);
        ObjectAnimator logoScaleX = ObjectAnimator.ofFloat(logo, "scaleX", 0.3f, 1f);
        ObjectAnimator logoScaleY = ObjectAnimator.ofFloat(logo, "scaleY", 0.3f, 1f);

        AnimatorSet logoSet = new AnimatorSet();
        logoSet.playTogether(logoAlpha, logoScaleX, logoScaleY);
        logoSet.setDuration(800);
        logoSet.setInterpolator(new OvershootInterpolator(1.2f));
        logoSet.start();

        // انیمیشن عنوان: fade in از پایین
        title.setAlpha(0f);
        title.setTranslationY(50f);
        title.animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(600)
                .setStartDelay(400)
                .setInterpolator(new DecelerateInterpolator())
                .start();

        // انیمیشن زیرعنوان
        subtitle.setAlpha(0f);
        subtitle.setTranslationY(30f);
        subtitle.animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(600)
                .setStartDelay(600)
                .setInterpolator(new DecelerateInterpolator())
                .start();

        // انیمیشن لودینگ
        loadingDots.setAlpha(0f);
        loadingDots.animate()
                .alpha(1f)
                .setDuration(400)
                .setStartDelay(800)
                .start();

        // رفتن به صفحه اصلی بعد از 2.5 ثانیه
        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            Intent intent = new Intent(SplashActivity.this, MainActivity.class);
            startActivity(intent);
            overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out);
            finish();
        }, 2500);
    }
}
