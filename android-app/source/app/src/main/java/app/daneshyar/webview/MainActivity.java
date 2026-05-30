package app.daneshyar.webview;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.KeyEvent;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.PermissionRequest;
import android.webkit.SslErrorHandler;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.RelativeLayout;
import android.widget.Toast;

/**
 * دانش‌یار - اپلیکیشن اصلی با WebView
 * ویژگی‌ها:
 * - صفحه خطای سفارشی (به‌جای نمایش URL)
 * - تشخیص خودکار وضعیت اینترنت
 * - مدیریت بهتر بازگشت
 * - عملکرد بهینه
 */
public class MainActivity extends Activity {

    // آدرس سرور - از دامنه استفاده می‌کنیم
    private static final String SERVER_URL = "https://daneshyar.ir/";

    private WebView webView;
    private RelativeLayout errorLayout;
    private ValueCallback<Uri[]> filePathCallback;
    private static final int FILE_CHOOSER_CODE = 1001;
    private long lastBackPress = 0;
    private boolean isErrorShown = false;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // تمام صفحه
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            Window window = getWindow();
            window.setStatusBarColor(0xFF0f1115);
            window.setNavigationBarColor(0xFF0f1115);
        }

        setContentView(R.layout.activity_main);

        // پیدا کردن ویوها
        webView = findViewById(R.id.webView);
        errorLayout = findViewById(R.id.error_layout);
        Button btnRetry = findViewById(R.id.btn_retry);

        // تنظیمات WebView
        setupWebView();

        // دکمه تلاش مجدد
        btnRetry.setOnClickListener(v -> retryLoading());

        // بارگذاری اولیه
        if (savedInstanceState == null) {
            loadUrl(SERVER_URL);
        } else {
            webView.restoreState(savedInstanceState);
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings s = webView.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setDatabaseEnabled(true);
        s.setLoadWithOverviewMode(true);
        s.setUseWideViewPort(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setAllowFileAccess(true);
        s.setAllowContentAccess(true);
        s.setCacheMode(WebSettings.LOAD_DEFAULT);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        s.setJavaScriptCanOpenWindowsAutomatically(true);

        // بهبود عملکرد
        s.setRenderPriority(WebSettings.RenderPriority.HIGH);
        s.setBlockNetworkImage(false);

        // پشتیبانی از زوم
        s.setSupportZoom(true);
        s.setBuiltInZoomControls(true);
        s.setDisplayZoomControls(false);

        CookieManager cm = CookieManager.getInstance();
        cm.setAcceptCookie(true);
        cm.setAcceptThirdPartyCookies(webView, true);

        // WebViewClient با مدیریت خطا
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                // مخفی کردن صفحه خطا هنگام شروع بارگذاری
                hideError();
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                // اگر صفحه با موفقیت بارگذاری شد
                if (!isErrorShown) {
                    webView.setVisibility(View.VISIBLE);
                }
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, 
                                        WebResourceError error) {
                super.onReceivedError(view, request, error);
                // فقط برای درخواست اصلی صفحه خطا نمایش بده
                if (request.isForMainFrame()) {
                    showError();
                }
            }

            @Override
            public void onReceivedSslError(WebView view, SslErrorHandler handler,
                                           android.net.http.SslError error) {
                // در production باید handler.cancel() باشد
                handler.proceed();
            }
        });

        // WebChromeClient برای آپلود فایل
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onPermissionRequest(final PermissionRequest request) {
                runOnUiThread(() -> request.grant(request.getResources()));
            }

            @Override
            public boolean onShowFileChooser(WebView wv, ValueCallback<Uri[]> callback,
                                             FileChooserParams params) {
                if (filePathCallback != null) filePathCallback.onReceiveValue(null);
                filePathCallback = callback;
                try {
                    Intent intent = params.createIntent();
                    intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
                    startActivityForResult(intent, FILE_CHOOSER_CODE);
                } catch (Exception e) {
                    filePathCallback = null;
                    Toast.makeText(MainActivity.this, 
                        "امکان باز کردن فایل نبود", Toast.LENGTH_SHORT).show();
                    return false;
                }
                return true;
            }
        });
    }

    /**
     * بارگذاری URL با بررسی اتصال
     */
    private void loadUrl(String url) {
        if (isNetworkAvailable()) {
            hideError();
            webView.loadUrl(url);
        } else {
            showError();
        }
    }

    /**
     * تلاش مجدد برای بارگذاری
     */
    private void retryLoading() {
        isErrorShown = false;
        if (isNetworkAvailable()) {
            webView.reload();
        } else {
            Toast.makeText(this, "اینترنت متصل نیست", Toast.LENGTH_SHORT).show();
            // تلاش مجدد بعد از 2 ثانیه
            new Handler(Looper.getMainLooper()).postDelayed(this::retryLoading, 2000);
        }
    }

    /**
     * نمایش صفحه خطا
     */
    private void showError() {
        isErrorShown = true;
        runOnUiThread(() -> {
            webView.setVisibility(View.GONE);
            errorLayout.setVisibility(View.VISIBLE);
        });
    }

    /**
     * مخفی کردن صفحه خطا
     */
    private void hideError() {
        isErrorShown = false;
        runOnUiThread(() -> {
            errorLayout.setVisibility(View.GONE);
            webView.setVisibility(View.VISIBLE);
        });
    }

    /**
     * بررسی اتصال اینترنت
     */
    private boolean isNetworkAvailable() {
        ConnectivityManager cm = (ConnectivityManager) 
            getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) return false;
        
        NetworkInfo activeNetwork = cm.getActiveNetworkInfo();
        return activeNetwork != null && activeNetwork.isConnected();
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == FILE_CHOOSER_CODE) {
            if (filePathCallback == null) return;
            Uri[] results = null;
            if (resultCode == Activity.RESULT_OK && data != null) {
                if (data.getDataString() != null) {
                    results = new Uri[]{ Uri.parse(data.getDataString()) };
                } else if (data.getClipData() != null) {
                    int count = data.getClipData().getItemCount();
                    results = new Uri[count];
                    for (int i = 0; i < count; i++) {
                        results[i] = data.getClipData().getItemAt(i).getUri();
                    }
                }
            }
            filePathCallback.onReceiveValue(results);
            filePathCallback = null;
        }
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_BACK) {
            // اگر صفحه خطا نمایش داده شده، از اپ خارج شو
            if (isErrorShown) {
                finish();
                return true;
            }
            
            if (webView.canGoBack()) {
                webView.goBack();
                return true;
            }
            
            // خروج با دوبار فشردن بازگشت
            if (System.currentTimeMillis() - lastBackPress < 2000) {
                finish();
                return true;
            }
            lastBackPress = System.currentTimeMillis();
            Toast.makeText(this, "برای خروج دوباره بازگشت را بزن", Toast.LENGTH_SHORT).show();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        webView.saveState(outState);
    }

    @Override
    protected void onResume() {
        super.onResume();
        webView.onResume();
        // بررسی مجدد اتصال هنگام بازگشت به اپ
        if (isErrorShown && isNetworkAvailable()) {
            retryLoading();
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        webView.onPause();
    }

    @Override
    protected void onDestroy() {
        if (webView != null) {
            webView.destroy();
        }
        super.onDestroy();
    }
}
