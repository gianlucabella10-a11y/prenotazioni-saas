plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.platform.client_app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // App Factory FASE 2B: identità parametrica per-tenant via Gradle
        // property (default = valori attuali → build standard invariata).
        // Es: flutter build appbundle -PAPP_ID=com.platform.t1a -PAPP_NAME="Giuffrida Barber"
        applicationId = (project.findProperty("APP_ID") as String?) ?: "com.platform.client_app"
        manifestPlaceholders["appName"] = (project.findProperty("APP_NAME") as String?) ?: "client_app"
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        // App Factory FASE 2C: firma release dai segreti CI letti da env.
        // Senza env (build locale / dev) resta vuota e si usa il debug → la
        // build standard non cambia. Nessun segreto nel repo.
        create("release") {
            val ksPath = System.getenv("ANDROID_KEYSTORE_PATH")
            if (ksPath != null && file(ksPath).exists()) {
                storeFile = file(ksPath)
                storePassword = System.getenv("ANDROID_KEYSTORE_PASSWORD")
                keyAlias = System.getenv("ANDROID_KEY_ALIAS")
                keyPassword = System.getenv("ANDROID_KEY_PASSWORD")
            }
        }
    }

    buildTypes {
        release {
            // Con keystore CI (env) firma release; altrimenti firma debug come
            // prima, così `flutter build/run --release` locale continua a funzionare.
            signingConfig = if (System.getenv("ANDROID_KEYSTORE_PATH") != null) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
