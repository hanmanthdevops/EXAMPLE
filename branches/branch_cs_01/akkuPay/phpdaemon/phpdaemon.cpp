#include <sys/types.h>
#include <sys/stat.h>
#include <stdio.h>
#include <stdlib.h>
#include <fcntl.h>
#include <errno.h>
#include <unistd.h>
#include <syslog.h>
#include <string.h>
#include <iostream>
#include <cstdlib>

using namespace std;

#define DAEMON_NAME "phpdaemon"

void process(int argc, char** argv){
    std::string message = std::string("Daemon PHP Process ") + argv[1] + std::string(" Started");
    syslog (LOG_NOTICE,message.c_str());
    std::string const command = std::string( "/usr/bin/php " ) + argv[1] +" >> /var/log/" + std::string(basename(argv[1])) + ".log";
    cout << std::system( command.c_str() );
    message = std::string("Daemon PHP Process ") + argv[1] + std::string(" Ended");
    syslog (LOG_NOTICE,message.c_str());
}   

void usage(int argc, char** argv) {
   cout << "Usage: " << argv[0] << " /path/to/script.php sleep_loop(seconds) \n";
}

int main(int argc, char *argv[]) {

   int sleepx = atoi(argv[2]);

   if ( argc <=2  ){
     cout << "Invalid Arguments OR No Arguments given \n";
     usage(argc, argv);
     exit(0);
   }
   else if (strcmp(argv[1], "--help") == 0) {
     usage(argc, argv);
     exit(0);
   }
   else


    //Set our Logging Mask and open the Log
    setlogmask(LOG_UPTO(LOG_NOTICE));
    openlog(DAEMON_NAME, LOG_CONS | LOG_NDELAY | LOG_PERROR | LOG_PID, LOG_USER);

    syslog(LOG_INFO, "Entering Daemon");

    pid_t pid, sid;

   //Fork the Parent Process
    pid = fork();

    if (pid < 0) { exit(EXIT_FAILURE); }

    //We got a good pid, Close the Parent Process
    if (pid > 0) { exit(EXIT_SUCCESS); }

    //Change File Mask
    umask(0);

    //Create a new Signature Id for our child
    sid = setsid();
    if (sid < 0) { exit(EXIT_FAILURE); }

    //Change Directory
    //If we cant find the directory we exit with failure.
    if ((chdir("/")) < 0) { exit(EXIT_FAILURE); }

    //Close Standard File Descriptors
    close(STDIN_FILENO);
    close(STDOUT_FILENO);
    close(STDERR_FILENO);

    //----------------
    //Main Process
    //----------------
    while(true){
        process(argc, argv);    //Run our Process
        sleep(sleepx);    //Sleep for 60 seconds
    }

    //Close the log
    closelog ();
}
