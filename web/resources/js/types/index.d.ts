// TypeScript interfaces mirroring the PHP DTOs

export interface FileDiffDTO {
  file: string;
  diff: string;
}

export interface TurnDTO {
  role: 'user' | 'assistant';
  content: string;
  timestamp: string;
  files_referenced: string[];
  file_diffs: FileDiffDTO[];
  reasoning_trace: string | null;
  tool_calls: Record<string, unknown>[];
}

export interface SessionDTO {
  id: string;
  title: string;
  project?: string;
  turn_count?: number;
  source_tool: string;
  source_machine_id: string;
  created_at: string;
  last_active_at: string;
  turns: TurnDTO[];
}

export interface AiSession {
  id: number;
  project_id: number | null;
  source_tool: string;
  session_id: string;
  title: string;
  exported_at: string | null;
  created_at: string;
  updated_at: string;
  project?: Project | null;
}

export interface Project {
  id: number;
  name: string;
  path: string | null;
  ai_sessions_count?: number;
  ai_sessions?: AiSession[];
}

export interface DashboardStats {
  total_sessions: number;
  total_projects: number;
  last_sync: string | null;
}

export type SyncStatus = 'synced' | 'pending' | 'error';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
}

/** Props shared with every page by HandleInertiaRequests. */
export interface SharedProps {
  auth: { user: AuthUser | null };
  [key: string]: unknown;
}
