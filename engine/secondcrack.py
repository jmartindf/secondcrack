#!/usr/bin/env python
import daemon
import lockfile
import time
import pyinotify
import subprocess

class EventHandler(pyinotify.ProcessEvent):
  def __init__(self, data):
    self.sites = data
    self.count = 0
    for site in self.sites:
      src = site[0]
      sc = site[1]
      inst = site[2]
      script = []
      script.append(sc + "/engine/update-new.sh")
      script.append(src)
      script.append(sc)
      script.append(inst)
      self.count += 1
      print "Executing for the %d time." % self.count
      subprocess.call(script)

  #def process_IN_DELETE(self, event):
  #  print "delete in "+event.pathname

  #def process_IN_MODIFY(self, event):
  #  print "modify in "+event.pathname

  #def process_IN_CREATE(self, event):
  #  print "create in "+event.pathname

  def process_default(self, event):
    for site in self.sites:
      src = site[0]
      sc = site[1]
      inst = site[2]
      if src in event.pathname:
        script = []
        script.append(sc + "/engine/update-new.sh")
        script.append(src)
        script.append(sc)
        script.append(inst)
        self.count += 1
        print "Executing for the %d time." % self.count
        subprocess.call(script)

def work():
  sites = [
      [ "/home/secondcrack/Dropbox/secondcrack/mt-dev",
        "/home/secondcrack/development/mt-dev",
        "sc-mt-dev" ],
      [ "/home/secondcrack/Dropbox/secondcrack/tm-dev",
        "/home/secondcrack/development/tm-dev",
        "sc-tm-dev" ],
      [ "/home/secondcrack/Dropbox/secondcrack/df-dev",
        "/home/secondcrack/development/df-dev",
        "sc-df-dev" ],
  ]
  wm = pyinotify.WatchManager()
  mask = pyinotify.IN_DELETE | pyinotify.IN_CREATE | pyinotify.IN_MODIFY | pyinotify.IN_ATTRIB | pyinotify.IN_CLOSE_WRITE  # watched events
  handler = EventHandler(sites)
  notifier = pyinotify.Notifier(wm, handler)
  for site in sites:
    wdd = wm.add_watch(site[0], mask, rec=True, auto_add=True)
  notifier.loop()

#context = daemon.DaemonContext(
#  working_directory = '/home/secondcrack',
#  pidfile=lockfile.FileLock('/home/secondcrack/sc.pid'),
#)
#
#with context:
#  work()
work()

