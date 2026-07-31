//go:build linux || darwin

package sqlitevec

/*
#cgo linux LDFLAGS: -ldl -lsqlite3
#cgo darwin LDFLAGS: -lsqlite3
#include <dlfcn.h>
#include <sqlite3.h>
#include <stdlib.h>

static int register_sqlite_vec(const char *path) {
	void *handle = dlopen(path, RTLD_NOW | RTLD_LOCAL);
	if (handle == NULL) return 1;
	void *entry = dlsym(handle, "sqlite3_vec_init");
	if (entry == NULL) return 2;
	return sqlite3_auto_extension((void (*)(void)) entry);
}
*/
import "C"

import (
	"fmt"
	"unsafe"

	"helmdesk/internal/localization"
)

// register 将 sqlite-vec 注册为当前进程的 SQLite 自动扩展。
func register(path string) error {
	cPath := C.CString(path)
	defer C.free(unsafe.Pointer(cPath))
	if code := C.register_sqlite_vec(cPath); code != C.SQLITE_OK {
		return fmt.Errorf(localization.Text("failed to load sqlite-vec: %d"), int(code))
	}
	return nil
}
