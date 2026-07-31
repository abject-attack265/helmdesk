/**
 * 文件说明：前端通用格式化工具，提供文件大小等与业务页面无关的展示转换。
 */

export function formatFileSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }

  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
