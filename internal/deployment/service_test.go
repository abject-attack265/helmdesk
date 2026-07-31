//go:build linux

package deployment

import (
	"strings"
	"testing"

	"helmdesk/internal/runtimeconfig"
)

func TestSystemdUnitGrantsManagedTLSPortCapability(t *testing.T) {
	unit, err := systemdUnit("/usr/local/bin/helmdesk", "/etc/helmdesk/config.json", runtimeconfig.Config{
		PublicURL:    "https://support.example.com",
		TLSMode:      runtimeconfig.TLSModeManaged,
		HTTPAddress:  "0.0.0.0:80",
		HTTPSAddress: "0.0.0.0:443",
		StoragePath:  "/var/lib/helmdesk",
	})
	if err != nil {
		t.Fatal(err)
	}
	for _, expected := range []string{
		"AmbientCapabilities=CAP_NET_BIND_SERVICE",
		"CapabilityBoundingSet=CAP_NET_BIND_SERVICE",
		"NoNewPrivileges=true",
	} {
		if !strings.Contains(unit, expected) {
			t.Fatalf("systemd unit 缺少 %q", expected)
		}
	}
}

func TestSystemdUnitKeepsPlainServiceUnprivileged(t *testing.T) {
	unit, err := systemdUnit("/usr/local/bin/helmdesk", "/etc/helmdesk/config.json", runtimeconfig.Config{
		PublicURL:   "http://127.0.0.1:8080",
		TLSMode:     runtimeconfig.TLSModePlain,
		HTTPAddress: "127.0.0.1:8080",
		StoragePath: "/var/lib/helmdesk",
	})
	if err != nil {
		t.Fatal(err)
	}
	if strings.Contains(unit, "CAP_NET_BIND_SERVICE") {
		t.Fatal("明文服务不应获得低端口能力")
	}
}
