package com.area3.dompisqe

import android.Manifest
import android.annotation.SuppressLint
import android.app.Activity
import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.net.Uri
import android.os.Bundle
import android.os.Environment
import android.os.Looper
import android.provider.MediaStore
import android.provider.Settings
import android.util.Log
import android.view.View
import android.webkit.*
import android.widget.ProgressBar
import android.widget.Toast

import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat

import java.io.File


/**
 * Wrapper WebView untuk Dompis QE.
 *
 * Perbaikan dari versi sebelumnya (lihat komentar bertanda FIX di tiap
 * bagian):
 *  - Kamera untuk upload foto/evidence SEBELUMNYA TIDAK PERNAH benar-benar
 *    terbuka - intent kamera dibuat tapi tidak pernah di-launch, jadi form
 *    upload foto di web selalu jatuh ke file picker biasa saja.
 *  - Hasil foto dari kamera SEBELUMNYA TIDAK ditangani di onActivityResult
 *    (kamera menyimpan hasil ke Uri lewat EXTRA_OUTPUT, bukan lewat
 *    data.data seperti file picker biasa) - kalaupun kamera sempat
 *    terbuka, hasil fotonya akan hilang / WebView menerima null.
 *  - Tidak ada dukungan multiple file selection (WebView bisa minta
 *    banyak file sekaligus, mis. multi-evidence upload).
 *  - setSupportMultipleWindows(true) diset tapi onCreateWindow tidak
 *    di-override - link/tombol yang membuka tab baru (target="_blank",
 *    window.open()) akan diam saja / tidak terjadi apa-apa.
 *  - Tidak ada permintaan izin CAMERA runtime.
 *  - Tidak ada penanganan saat GPS di-nonaktifkan (izin lokasi granted
 *    tapi GPS off -> tag lokasi di web akan gagal diam-diam tanpa
 *    penjelasan ke user).
 */
class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private lateinit var progressBar: ProgressBar

    private var fileCallback: ValueCallback<Array<Uri>>? = null
    private var cameraPhotoUri: Uri? = null

    private var geoOrigin: String? = null
    private var geoCallback: GeolocationPermissions.Callback? = null

    // FIX (bridge lokasi native): sebagian device/versi Android System WebView
    // menolak navigator.geolocation() di halaman HTTPS yang sah dengan pesan
    // "Only secure origins are allowed" - ini bug/pembatasan di implementasi
    // WebView itu sendiri (bukan di halaman web/sertifikat), dan browser HP biasa
    // (Chrome) tidak kena masalah yang sama karena versi WebView-nya berbeda.
    // Solusi permanen: minta lokasi lewat LocationManager NATIVE Android (lewat
    // JavascriptInterface "AndroidLocation"), lalu suntik hasilnya ke JS - ini
    // sepenuhnya melewati (bypass) navigator.geolocation browser di dalam WebView,
    // jadi tidak tergantung versi/vendor WebView di HP masing-masing teknisi.
    private var nativeLocationListener: LocationListener? = null
    private var nativeLocationTimeoutRunnable: Runnable? = null
    private var pendingNativeLocationRequest = false

    private val LOCATION_REQUEST = 2001
    private val FILE_REQUEST = 2002
    private val CAMERA_PERMISSION_REQUEST = 2003
    private val NATIVE_LOCATION_PERMISSION_REQUEST = 2004

    private val url = "https://dompisqe.telkomakses-area3.id/"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        setContentView(R.layout.activity_main)

        // FIX: dipanggil SETELAH setContentView (bukan sebelum) - hideSystemUI()
        // butuh window.decorView sudah dibuat. Fungsi ini sendiri sekarang pakai
        // WindowCompat/WindowInsetsControllerCompat dari AndroidX (lihat komentar
        // di hideSystemUI()) supaya tidak crash NullPointerException seperti
        // sebelumnya di sebagian device/versi Android.
        hideSystemUI()

        webView = findViewById(R.id.webView)
        progressBar = findViewById(R.id.progressBar)

        requestStartupPermissions()

        setupWebView()

        // FIX (bridge lokasi native): daftarkan interface "AndroidLocation" supaya
        // JS di halaman web (project.blade.php) bisa panggil
        // AndroidLocation.requestLocation() sebagai fallback saat
        // navigator.geolocation() browser gagal dengan "Only secure origins are
        // allowed". Aman untuk https resmi kita sendiri (bukan web sembarangan).
        webView.addJavascriptInterface(LocationBridge(this), "AndroidLocation")

        webView.loadUrl(url)

        onBackPressedDispatcher.addCallback(
            this,
            object : OnBackPressedCallback(true) {
                override fun handleOnBackPressed() {
                    if (webView.canGoBack()) {
                        webView.goBack()
                    } else {
                        finish()
                    }
                }
            }
        )
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {

        val settings = webView.settings

        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.databaseEnabled = true
        settings.allowFileAccess = true
        settings.allowContentAccess = true
        settings.setGeolocationEnabled(true)
        settings.javaScriptCanOpenWindowsAutomatically = true
        settings.setSupportMultipleWindows(true)
        settings.mediaPlaybackRequiresUserGesture = false

        // FIX (optimasi smoothness): sebelumnya tidak diset sama sekali.
        // LOAD_DEFAULT memakai cache HTTP normal (CSS/JS/gambar tidak didownload
        // ulang tiap pindah menu selama server mengirim header cache yang benar).
        // Hardware layer + mematikan efek overscroll/glow bikin transisi scroll &
        // pindah halaman terasa lebih ringan/native, bukan seperti browser biasa.
        settings.cacheMode = WebSettings.LOAD_DEFAULT
        settings.offscreenPreRaster = true
        webView.setLayerType(View.LAYER_TYPE_HARDWARE, null)
        webView.overScrollMode = View.OVER_SCROLL_NEVER

        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        webView.webViewClient = object : WebViewClient() {

            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val requestUrl = request?.url ?: return false
                val scheme = requestUrl.scheme

                // FIX: link non-http/https (tel:, mailto:, whatsapp:, intent:, dll -
                // umum dipakai utk "hubungi admin" / share evidence) diarahkan ke
                // aplikasi eksternal yang sesuai, bukan dibiarkan gagal senyap di WebView.
                if (scheme != "http" && scheme != "https") {
                    return try {
                        startActivity(Intent(Intent.ACTION_VIEW, requestUrl))
                        true
                    } catch (e: Exception) {
                        Toast.makeText(this@MainActivity, "Tidak ada aplikasi untuk membuka tautan ini", Toast.LENGTH_SHORT).show()
                        true
                    }
                }

                return false
            }
        }

        webView.webChromeClient = object : WebChromeClient() {

            override fun onProgressChanged(view: WebView?, progress: Int) {
                progressBar.visibility = if (progress < 100) View.VISIBLE else View.GONE
            }

            // FIX: sebelumnya tidak ada sama sekali - error JS (termasuk kode error
            // asli navigator.geolocation: 1=PERMISSION_DENIED, 2=POSITION_UNAVAILABLE,
            // 3=TIMEOUT) tidak pernah terlihat, cuma pesan generik di halaman web.
            // Cek Logcat dengan filter tag "DompisQE-Console" untuk lihat pesan asli
            // saat tombol "Gunakan lokasi saat ini" gagal.
            override fun onConsoleMessage(message: ConsoleMessage?): Boolean {
                message?.let {
                    Log.d(
                        "DompisQE-Console",
                        "${it.message()} -- ${it.sourceId()}:${it.lineNumber()}"
                    )
                }
                return true
            }

            // FIX: wajib di-override saat setSupportMultipleWindows(true) - tanpa ini,
            // target="_blank" / window.open() di halaman web (mis. buka PDF/preview
            // evidence di tab baru) tidak melakukan apa-apa.
            override fun onCreateWindow(
                view: WebView?,
                isDialog: Boolean,
                isUserGesture: Boolean,
                resultMsg: android.os.Message?
            ): Boolean {
                val newWebView = WebView(this@MainActivity)
                newWebView.webViewClient = object : WebViewClient() {
                    override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                        val u = request?.url ?: return false
                        webView.loadUrl(u.toString())
                        return true
                    }
                }
                val transport = resultMsg?.obj as? WebView.WebViewTransport
                transport?.webView = newWebView
                resultMsg?.sendToTarget()
                return true
            }

            override fun onGeolocationPermissionsShowPrompt(
                origin: String?,
                callback: GeolocationPermissions.Callback?
            ) {
                val locationGranted =
                    ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
                        ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED

                val manager = getSystemService(LOCATION_SERVICE) as LocationManager
                val gpsActive = manager.isProviderEnabled(LocationManager.GPS_PROVIDER) ||
                    manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)

                if (locationGranted && gpsActive) {
                    callback?.invoke(origin, true, false)
                    return
                }

                if (locationGranted && !gpsActive) {
                    // FIX PENTING: izin APLIKASI (Location permission di App Info)
                    // dan toggle LOKASI PERANGKAT (Settings > Location) adalah dua hal
                    // berbeda - keduanya wajib aktif. Sebelumnya di sini kita tetap
                    // kirim allow=true ke WebView walau GPS perangkat mati, sehingga
                    // navigator.geolocation di web tetap mencoba lalu gagal lambat
                    // dengan pesan generik ("Lokasi gagal diambil..."). Sekarang kita
                    // tolak (allow=false) supaya web langsung dapat error cepat & jelas,
                    // sambil dialog native mengarahkan user ke Settings > Location.
                    callback?.invoke(origin, false, false)
                    promptEnableGps()
                    return
                }

                geoOrigin = origin
                geoCallback = callback
                requestLocationPermission()
            }

            override fun onShowFileChooser(
                webView: WebView?,
                callback: ValueCallback<Array<Uri>>?,
                params: FileChooserParams?
            ): Boolean {

                fileCallback?.onReceiveValue(null)
                fileCallback = callback

                val allowMultiple = params?.mode == FileChooserParams.MODE_OPEN_MULTIPLE
                val acceptTypes = params?.acceptTypes
                    ?.filter { it.isNotBlank() }
                    ?.toTypedArray()
                    ?: emptyArray()
                val mimeType = if (acceptTypes.isNotEmpty()) acceptTypes.joinToString(",") else "*/*"

                // Intent kamera - dibuat SETIAP kali dipanggil (bukan sekali di awal)
                // supaya file sementara & Uri-nya selalu baru per pemilihan.
                var cameraIntent: Intent? = null
                val hasCameraPermission =
                    ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED

                if (hasCameraPermission) {
                    val takePicture = Intent(MediaStore.ACTION_IMAGE_CAPTURE)
                    if (takePicture.resolveActivity(packageManager) != null) {
                        val photoFile = File.createTempFile("dompisqe_", ".jpg", cacheDir)
                        val photoUri = FileProvider.getUriForFile(
                            this@MainActivity,
                            "$packageName.provider",
                            photoFile
                        )
                        cameraPhotoUri = photoUri
                        takePicture.putExtra(MediaStore.EXTRA_OUTPUT, photoUri)
                        takePicture.addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION)
                        cameraIntent = takePicture
                    }
                } else {
                    // Minta izin kamera untuk pemilihan berikutnya; pemilihan saat ini
                    // tetap lanjut lewat file picker biasa supaya user tidak buntu.
                    ActivityCompat.requestPermissions(
                        this@MainActivity,
                        arrayOf(Manifest.permission.CAMERA),
                        CAMERA_PERMISSION_REQUEST
                    )
                }

                // FIX: intent file picker sekarang benar-benar menghormati accept type
                // dari <input accept="image/*">, dan mendukung pilih banyak file
                // (params.mode MODE_OPEN_MULTIPLE) yang sebelumnya diabaikan.
                val filePickerIntent = Intent(Intent.ACTION_GET_CONTENT).apply {
                    type = mimeType
                    addCategory(Intent.CATEGORY_OPENABLE)
                    if (allowMultiple) putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true)
                }

                val chooserIntent = Intent.createChooser(filePickerIntent, "Pilih atau Ambil Foto")

                // FIX: kamera sekarang benar-benar ditawarkan sebagai opsi di chooser
                // (sebelumnya intent kamera dibuat tapi tidak pernah dipasang/dijalankan
                // sama sekali).
                if (cameraIntent != null) {
                    chooserIntent.putExtra(Intent.EXTRA_INITIAL_INTENTS, arrayOf(cameraIntent))
                }

                startActivityForResult(chooserIntent, FILE_REQUEST)

                return true
            }
        }

        webView.setDownloadListener { downloadUrl, userAgent, contentDisposition, mimeType, _ ->

            val request = DownloadManager.Request(Uri.parse(downloadUrl))
            request.addRequestHeader("User-Agent", userAgent)
            request.setTitle(URLUtil.guessFileName(downloadUrl, contentDisposition, mimeType))
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, URLUtil.guessFileName(downloadUrl, contentDisposition, mimeType))

            val manager = getSystemService(DOWNLOAD_SERVICE) as DownloadManager
            manager.enqueue(request)

            Toast.makeText(this, "Download dimulai", Toast.LENGTH_SHORT).show()
        }
    }

    private fun requestStartupPermissions() {
        requestLocationPermission()

        // FIX: izin kamera sebelumnya tidak pernah diminta sama sekali - diminta
        // di awal supaya saat user pertama kali upload foto evidence, kamera
        // langsung bisa dipakai tanpa perlu retry.
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.CAMERA), CAMERA_PERMISSION_REQUEST)
        }

        // FIX: kalau izin lokasi sudah granted sejak awal (mis. install ulang /
        // sudah pernah diizinkan sebelumnya), langsung "panaskan" GPS di sini juga -
        // jangan hanya menunggu izin baru di onRequestPermissionsResult.
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED) {
            warmUpGps()
        }
    }

    /**
     * FIX: sebelumnya tidak ada sama sekali. Meminta satu location update native
     * di background begitu app dibuka (bukan menunggu tombol "Gunakan lokasi saat
     * ini" ditekan di web) supaya chip GPS sudah "warm"/sudah punya sinyal saat
     * navigator.geolocation() dipanggil dari WebView - ini penyebab paling umum
     * kegagalan/timeout ambil lokasi pertama kali (GPS cold-start bisa makan
     * puluhan detik). Auto-berhenti setelah dapat 1 fix atau 20 detik supaya
     * tidak menguras baterai.
     */
    @SuppressLint("MissingPermission")
    private fun warmUpGps() {
        val manager = getSystemService(LOCATION_SERVICE) as LocationManager

        val provider = when {
            manager.isProviderEnabled(LocationManager.GPS_PROVIDER) -> LocationManager.GPS_PROVIDER
            manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER) -> LocationManager.NETWORK_PROVIDER
            else -> return
        }

        val listener = object : LocationListener {
            override fun onLocationChanged(location: Location) {
                manager.removeUpdates(this)
            }

            @Deprecated("Deprecated in Java")
            override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
            override fun onProviderEnabled(provider: String) {}
            override fun onProviderDisabled(provider: String) {}
        }

        try {
            manager.requestLocationUpdates(provider, 1000L, 0f, listener, Looper.getMainLooper())

            webView.postDelayed({
                try { manager.removeUpdates(listener) } catch (_: Exception) {}
            }, 20_000L)
        } catch (e: SecurityException) {
            // Izin dicabut di antara pengecekan dan pemanggilan - abaikan, WebView
            // akan tetap meminta izin sendiri lewat onGeolocationPermissionsShowPrompt.
        }
    }

    private fun requestLocationPermission() {
        val list = mutableListOf<String>()

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            list.add(Manifest.permission.ACCESS_FINE_LOCATION)
        }
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            list.add(Manifest.permission.ACCESS_COARSE_LOCATION)
        }

        if (list.isNotEmpty()) {
            ActivityCompat.requestPermissions(this, list.toTypedArray(), LOCATION_REQUEST)
        }
    }

    private fun promptEnableGps() {
        AlertDialog.Builder(this)
            .setTitle("Aktifkan Lokasi")
            .setMessage("Fitur tag lokasi memerlukan GPS aktif. Aktifkan Lokasi di pengaturan perangkat?")
            .setPositiveButton("Buka Pengaturan") { _, _ ->
                startActivity(Intent(Settings.ACTION_LOCATION_SOURCE_SETTINGS))
            }
            .setNegativeButton("Nanti", null)
            .show()
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)

        if (requestCode == LOCATION_REQUEST) {
            val granted = grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED

            val manager = getSystemService(LOCATION_SERVICE) as LocationManager
            val gpsActive = manager.isProviderEnabled(LocationManager.GPS_PROVIDER) ||
                manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)

            if (granted && !gpsActive) {
                promptEnableGps()
            } else if (granted) {
                warmUpGps()
            }

            // FIX: konsisten dengan onGeolocationPermissionsShowPrompt - jangan
            // invoke(true) kalau GPS perangkat masih mati.
            geoCallback?.invoke(geoOrigin, granted && gpsActive, false)
            geoCallback = null
            geoOrigin = null

            // FIX (bridge lokasi native): kalau permintaan izin ini berasal dari
            // AndroidLocation.requestLocation() (bukan dari prompt geolocation browser),
            // lanjutkan proses ambil lokasi native sekarang izin sudah dijawab -
            // jangan diam saja / tidak ada balasan ke JS.
            if (pendingNativeLocationRequest) {
                pendingNativeLocationRequest = false
                if (!granted) {
                    sendNativeLocationError(1, "Izin lokasi ditolak")
                } else if (!gpsActive) {
                    sendNativeLocationError(2, "GPS/Lokasi perangkat tidak aktif")
                    promptEnableGps()
                } else {
                    requestNativeLocation()
                }
            }
        }

        if (requestCode == CAMERA_PERMISSION_REQUEST) {
            val granted = grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED
            if (!granted) {
                Toast.makeText(this, "Izin kamera ditolak - upload foto hanya bisa lewat galeri", Toast.LENGTH_LONG).show()
            }
        }
    }

    @Deprecated("Deprecated in Java")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)

        if (requestCode != FILE_REQUEST) return

        if (resultCode != Activity.RESULT_OK) {
            fileCallback?.onReceiveValue(null)
            fileCallback = null
            cameraPhotoUri = null
            return
        }

        // FIX: sebelumnya hanya data?.data yang dicek - hasil foto dari kamera
        // TIDAK PERNAH sampai ke WebView krn kamera menyimpan hasilnya ke Uri
        // yang sudah kita siapkan lewat EXTRA_OUTPUT (cameraPhotoUri), bukan
        // lewat data.data (yang untuk kamera biasanya null).
        val result: Array<Uri>? = when {
            data?.clipData != null -> {
                // Multi-select dari galeri.
                val clipData = data.clipData!!
                Array(clipData.itemCount) { i -> clipData.getItemAt(i).uri }
            }
            data?.data != null -> arrayOf(data.data!!)
            cameraPhotoUri != null -> arrayOf(cameraPhotoUri!!)
            else -> null
        }

        fileCallback?.onReceiveValue(result)
        fileCallback = null
        cameraPhotoUri = null
    }

    /**
     * FIX: versi lama pakai window.insetsController (API mentah platform)
     * langsung di API 30+, yang men-crash dengan NullPointerException di
     * PhoneWindow.getInsetsController() kalau DecorView belum ter-attach
     * penuh ke window saat dipanggil (terjadi di sebagian device/timing,
     * termasuk yang Anda alami barusan) - lihat stack trace:
     * "Attempt to invoke virtual method ... getWindowInsetsController() on
     * a null object reference".
     *
     * WindowCompat.getInsetsController() dari AndroidX menghindari masalah
     * ini sepenuhnya (aman dipanggil kapan pun setelah setContentView, dan
     * otomatis fallback ke systemUiVisibility lama di API < 30) - jadi
     * seluruh percabangan SDK_INT manual sebelumnya tidak diperlukan lagi.
     */
    private fun hideSystemUI() {
        WindowCompat.setDecorFitsSystemWindows(window, false)

        val controller = WindowCompat.getInsetsController(window, window.decorView)
        controller.hide(WindowInsetsCompat.Type.systemBars())
        controller.systemBarsBehavior =
            WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
    }

    /**
     * FIX (bridge lokasi native): JS interface yang dipanggil dari web lewat
     * `AndroidLocation.requestLocation()`. Dipakai sebagai fallback ketika
     * navigator.geolocation() browser di dalam WebView gagal dengan
     * "Only secure origins are allowed" walau halamannya sudah HTTPS -
     * ini murni keterbatasan implementasi WebView, jadi kita ambil lokasi
     * langsung lewat LocationManager Android (di luar jalur browser sama sekali).
     *
     * @JavascriptInterface WAJIB dijalankan bukan di main thread (kontrak WebView),
     * jadi langsung post ke main thread sebelum sentuh LocationManager/UI.
     */
    private inner class LocationBridge(private val activity: MainActivity) {
        @JavascriptInterface
        fun requestLocation() {
            activity.runOnUiThread { activity.requestNativeLocation() }
        }
    }

    /**
     * Minta 1 fix lokasi lewat LocationManager native, lalu kirim hasilnya ke JS
     * lewat window.__androidLocationResult(lat, lng, accuracy) atau
     * window.__androidLocationError(code, message) - kode error dibuat SAMA
     * dengan PositionError standar (1=PERMISSION_DENIED, 2=POSITION_UNAVAILABLE,
     * 3=TIMEOUT) supaya JS di project.blade.php bisa pakai penanganan pesan
     * error yang sama persis dengan jalur navigator.geolocation().
     */
    private fun requestNativeLocation() {
        cancelNativeLocationRequest()

        val fineGranted = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
        val coarseGranted = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED

        if (!fineGranted && !coarseGranted) {
            pendingNativeLocationRequest = true
            requestLocationPermission()
            return
        }

        val manager = getSystemService(LOCATION_SERVICE) as LocationManager
        val provider = when {
            manager.isProviderEnabled(LocationManager.GPS_PROVIDER) -> LocationManager.GPS_PROVIDER
            manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER) -> LocationManager.NETWORK_PROVIDER
            else -> null
        }

        if (provider == null) {
            sendNativeLocationError(2, "GPS/Lokasi perangkat tidak aktif")
            promptEnableGps()
            return
        }

        startNativeLocationUpdates(manager, provider)
    }

    @SuppressLint("MissingPermission")
    private fun startNativeLocationUpdates(manager: LocationManager, provider: String) {
        // Pakai last known location dulu kalau masih cukup segar (< 30 detik) -
        // ini yang bikin balasan terasa instan pada percobaan kedua dst, alih-alih
        // selalu menunggu GPS fix baru dari nol.
        val lastKnown = try { manager.getLastKnownLocation(provider) } catch (e: SecurityException) { null }
        if (lastKnown != null && (System.currentTimeMillis() - lastKnown.time) < 30_000L) {
            sendNativeLocationResult(lastKnown)
            return
        }

        val listener = object : LocationListener {
            override fun onLocationChanged(location: Location) {
                sendNativeLocationResult(location)
                cancelNativeLocationRequest()
            }

            @Deprecated("Deprecated in Java")
            override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
            override fun onProviderEnabled(provider: String) {}
            override fun onProviderDisabled(provider: String) {
                sendNativeLocationError(2, "GPS/Lokasi perangkat dimatikan")
                cancelNativeLocationRequest()
            }
        }
        nativeLocationListener = listener

        try {
            manager.requestLocationUpdates(provider, 1000L, 0f, listener, Looper.getMainLooper())
        } catch (e: SecurityException) {
            sendNativeLocationError(1, "Izin lokasi ditolak")
            nativeLocationListener = null
            return
        }

        // FIX: batas waktu 25 detik, konsisten dengan timeout navigator.geolocation
        // di project.blade.php - kalau tidak dapat fix sama sekali dalam waktu ini,
        // kirim TIMEOUT (kode 3) ke JS supaya user lihat pesan yang sama seperti
        // jalur browser biasa.
        val timeoutRunnable = Runnable {
            sendNativeLocationError(3, "Timeout mengambil lokasi (25 detik)")
            cancelNativeLocationRequest()
        }
        nativeLocationTimeoutRunnable = timeoutRunnable
        webView.postDelayed(timeoutRunnable, 25_000L)
    }

    private fun cancelNativeLocationRequest() {
        nativeLocationListener?.let {
            try {
                (getSystemService(LOCATION_SERVICE) as LocationManager).removeUpdates(it)
            } catch (_: Exception) {}
        }
        nativeLocationListener = null

        nativeLocationTimeoutRunnable?.let { webView.removeCallbacks(it) }
        nativeLocationTimeoutRunnable = null
    }

    private fun sendNativeLocationResult(location: Location) {
        val js = "javascript:window.__androidLocationResult && window.__androidLocationResult(" +
            "${location.latitude}, ${location.longitude}, ${location.accuracy})"
        webView.evaluateJavascript(js, null)
    }

    private fun sendNativeLocationError(code: Int, message: String) {
        val safeMessage = message.replace("\"", "'")
        val js = "javascript:window.__androidLocationError && window.__androidLocationError(" +
            "$code, \"$safeMessage\")"
        webView.evaluateJavascript(js, null)
    }

    override fun onDestroy() {
        cancelNativeLocationRequest()
        if (::webView.isInitialized) {
            webView.stopLoading()
            webView.destroy()
        }
        super.onDestroy()
    }
}
