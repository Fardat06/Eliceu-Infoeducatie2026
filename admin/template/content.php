        <div class="dashboard-content">
          <!-- Overview View -->
          <div class="dashboard-view active" id="overview">
            <!-- Stats Cards -->
            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-card-header">
                  <div class="stat-card-title">Total Projects</div>
                  <div class="stat-card-icon primary">
                    <span class="material-symbols-rounded">folder</span>
                  </div>
                </div>
                <div class="stat-card-value">12</div>
                <div class="stat-card-change positive">
                  <span class="material-symbols-rounded">trending_up</span>
                  <span>+2 this week</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-card-header">
                  <div class="stat-card-title">Completed Tasks</div>
                  <div class="stat-card-icon success">
                    <span class="material-symbols-rounded">check_circle</span>
                  </div>
                </div>
                <div class="stat-card-value">48</div>
                <div class="stat-card-change positive">
                  <span class="material-symbols-rounded">trending_up</span>
                  <span>+15% from last week</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-card-header">
                  <div class="stat-card-title">Pending Tasks</div>
                  <div class="stat-card-icon warning">
                    <span class="material-symbols-rounded">schedule</span>
                  </div>
                </div>
                <div class="stat-card-value">23</div>
                <div class="stat-card-change negative">
                  <span class="material-symbols-rounded">trending_down</span>
                  <span>-3 from last week</span>
                </div>
              </div>
              <div class="stat-card">
                <div class="stat-card-header">
                  <div class="stat-card-title">Team Members</div>
                  <div class="stat-card-icon info">
                    <span class="material-symbols-rounded">group</span>
                  </div>
                </div>
                <div class="stat-card-value">8</div>
                <div class="stat-card-change positive">
                  <span class="material-symbols-rounded">trending_up</span>
                  <span>+1 new member</span>
                </div>
              </div>
            </div>
            <!-- Charts -->
            <div class="chart-grid">
              <div class="chart-card">
                <div class="chart-card-header">
                  <h3 class="chart-card-title">Project Progress</h3>
                  <p class="chart-card-subtitle">Completion rate over time</p>
                </div>
                <div class="chart-container">
                  <canvas id="progressChart"></canvas>
                </div>
              </div>
              <div class="chart-card">
                <div class="chart-card-header">
                  <h3 class="chart-card-title">Task Distribution</h3>
                  <p class="chart-card-subtitle">Tasks by category</p>
                </div>
                <div class="chart-container">
                  <canvas id="categoryChart"></canvas>
                </div>
              </div>
            </div>
            <!-- Recent Activity -->
            <div class="dashboard-table-container">
              <div class="dashboard-table-header">
                <h3 class="dashboard-table-title">Recent Projects</h3>
                <a href="#" class="btn btn-secondary">View All</a>
              </div>
              <table class="dashboard-table">
                <thead>
                  <tr>
                    <th>Project</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Due Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <div class="project-title-cell">
                        <div class="project-icon">
                          <span class="material-symbols-rounded">web</span>
                        </div>
                        <div class="project-info">
                          <div class="project-title-text">Website Redesign</div>
                          <div class="project-meta-text">Frontend • 5 tasks</div>
                        </div>
                      </div>
                    </td>
                    <td>85%</td>
                    <td><span class="status-badge success">In Progress</span></td>
                    <td>Dec 15, 2024</td>
                  </tr>
                  <tr>
                    <td>
                      <div class="project-title-cell">
                        <div class="project-icon">
                          <span class="material-symbols-rounded">phone_android</span>
                        </div>
                        <div class="project-info">
                          <div class="project-title-text">Mobile App</div>
                          <div class="project-meta-text">Mobile • 8 tasks</div>
                        </div>
                      </div>
                    </td>
                    <td>60%</td>
                    <td><span class="status-badge warning">In Progress</span></td>
                    <td>Jan 20, 2025</td>
                  </tr>
                  <tr>
                    <td>
                      <div class="project-title-cell">
                        <div class="project-icon">
                          <span class="material-symbols-rounded">database</span>
                        </div>
                        <div class="project-info">
                          <div class="project-title-text">Database Migration</div>
                          <div class="project-meta-text">Backend • 3 tasks</div>
                        </div>
                      </div>
                    </td>
                    <td>100%</td>
                    <td><span class="status-badge success">Completed</span></td>
                    <td>Nov 30, 2024</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <!-- Projects View -->
          <div class="dashboard-view" id="projects">
            <div class="empty-state">
              <div class="empty-state-icon">
                <span class="material-symbols-rounded">folder</span>
              </div>
              <h3 class="empty-state-title">Projects</h3>
              <p class="empty-state-description">Manage your projects here. Create new projects, track progress, and collaborate with your team.</p>
            </div>
          </div>
          <!-- Tasks View -->
          <div class="dashboard-view" id="tasks">
            <div class="empty-state">
              <div class="empty-state-icon">
                <span class="material-symbols-rounded">checklist</span>
              </div>
              <h3 class="empty-state-title">Tasks</h3>
              <p class="empty-state-description">View and manage all your tasks. Create new tasks, set priorities, and track completion status.</p>
            </div>
          </div>
          <!-- Reports View -->
          <div class="dashboard-view" id="reports">
            <div class="empty-state">
              <div class="empty-state-icon">
                <span class="material-symbols-rounded">bar_chart</span>
              </div>
              <h3 class="empty-state-title">Reports</h3>
              <p class="empty-state-description">Generate detailed reports and analytics. View project performance, team productivity, and time tracking data.</p>
            </div>
          </div>
          <!-- Settings View -->
          <div class="dashboard-view" id="settings">
            <div class="empty-state">
              <div class="empty-state-icon">
                <span class="material-symbols-rounded">settings</span>
              </div>
              <h3 class="empty-state-title">Settings</h3>
              <p class="empty-state-description">Configure your dashboard preferences, manage team members, and customize your workspace.</p>
            </div>
          </div>
        </div>
