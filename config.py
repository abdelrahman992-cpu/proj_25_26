import platform

def get_system_settings():
    os_name = platform.system()  # يرجع 'Windows' أو 'Linux'
    
    if os_name == "Windows":
        return {
            "os": "Windows",
            "env_file": "2.env",
            "db_host": "localhost",
            "path_separator": "\\"
        }
    else:  # Linux
        return {
            "os": "Linux",
            "env_file": ".env", # غالباً في لينكس نستخدم .env
            "db_host": "127.0.0.1",
            "path_separator": "/"
        }

settings = get_system_settings()
print(f"نظام التشغيل المكتشف: {settings['os']}")