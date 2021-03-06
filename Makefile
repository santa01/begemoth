#
# Copyright (c) 2013 Pavlo Lavrenenko
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#

PREFIX := /usr/local
RUNDIR := $(PREFIX)/var/run
LIBDIR := $(PREFIX)/lib
BINDIR := $(PREFIX)/bin
CFGDIR := $(PREFIX)/etc

INITDIR := $(CFGDIR)/systemd/system
JAXLDIR := $(PREFIX)/share/php/jaxl

TARGET := begemoth
SOURCES := $(shell find $(CURDIR) -name '*.in')
OBJECTS := $(patsubst %.in, %, $(SOURCES))

all: $(TARGET)

$(TARGET): $(OBJECTS)

%: %.in
	cp $< $(<:%.in=%)
	sed -i \
		-e 's|%RUNDIR%|$(RUNDIR)|g' \
		-e 's|%LIBDIR%|$(LIBDIR)|g' \
		-e 's|%BINDIR%|$(BINDIR)|g' \
		-e 's|%CFGDIR%|$(CFGDIR)|g' \
		-e 's|%JAXLDIR%|$(JAXLDIR)|g' $(<:%.in=%)

install: $(TARGET)
	mkdir -p $(DESTDIR)/$(BINDIR) \
		$(DESTDIR)/$(RUNDIR)/$(TARGET) \
		$(DESTDIR)/$(LIBDIR)/$(TARGET) \
		$(DESTDIR)/$(CFGDIR)/$(TARGET) \
		$(DESTDIR)/$(INITDIR)

	cp -r src/plugins $(DESTDIR)/$(LIBDIR)/$(TARGET)
	cp config/*.json $(DESTDIR)/$(CFGDIR)/$(TARGET)
	cp contrib/*.service $(DESTDIR)/$(INITDIR)
	cp src/*.php $(DESTDIR)/$(LIBDIR)/$(TARGET)
	cp src/$(TARGET) $(DESTDIR)/$(BINDIR)
	chmod +x $(DESTDIR)/$(BINDIR)/$(TARGET)

clean:
	rm -f $(OBJECTS)
