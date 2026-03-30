// Shared TypeScript types mirroring the PHP DTOs

export interface FileDiffDTO {
  file: string;
  diff: string;
}

export interface TurnDTO {
  role: 'user' | 'assistant';
  content: string;
  timestamp: string; // ISO 8601
  files_referenced: string[];
  file_diffs: FileDiffDTO[];
  reasoning_trace: string | null;
  tool_calls: Record<string, unknown>[];
}

export interface SessionDTO {
  id: string;
  title: string;
  source_tool: string;
  source_machine_id: string;
  created_at: string;   // ISO 8601
  last_active_at: string;
  turns: TurnDTO[];
}

export interface ProjectConfigDTO {
  cursor_rules: string | null;
  claude_md: string | null;
  mcp_json: string | null;
  agents_md: string | null;
}
