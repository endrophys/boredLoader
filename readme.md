# boredLoader

- Made this in two afternoons while being bored.
- Don't use this for evil. Learn from it and make it yours — or don't.
- I don't accept responsibility for any harm caused by this educational project.

## Panel

- Download the repo and go into the project directory.
- Copy the file "index.php" from the C2 directory and host it on your preferred hosting platform.
- After uploading, modify index.php and update the DATABASE info and the DEFAULT PASSWORD (follow the instructions in the file).
- All database tables are created automatically; just make sure you have a valid database.

## BOT

- The bot is located in the client directory and is written in Rust.
- Open the file named main.rs inside the src folder and modify the domain and sleep time if needed (the panel assumes a 30-second sleep time).
- After editing, save the file and open a terminal in the bot source folder (the folder containing Cargo.toml).
- In the terminal, make sure [Rust is installed](https://rust-lang.org/tools/install/) and run ```cargo build --release```
- After compilation, you'll find the compiled bot at target/release/client.exe. That is your compiled bot.

## Contact
- https://t.me/botnetloader